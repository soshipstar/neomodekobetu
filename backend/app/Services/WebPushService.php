<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Web Push通知サービス
 *
 * Pure PHP実装: VAPID (RFC 8292) + Message Encryption (RFC 8291)
 * 外部ライブラリ不要（openssl拡張のみ必要）
 */
class WebPushService
{
    private ?string $publicKey;

    private ?string $privateKey;

    private string $subject;

    public function __construct()
    {
        // VAPID キーが未設定の環境（dev / test）では null のままにして、
        // sendToUser が呼ばれても早期 return で無視する（プロパティ型制約で
        // 落ちないようにする）
        $this->publicKey = config('services.webpush.public_key') ?: null;
        $this->privateKey = config('services.webpush.private_key') ?: null;
        $this->subject = config('services.webpush.subject', 'mailto:admin@kiduri.xyz');
    }

    /**
     * VAPID キーが設定されているかどうか。未設定なら push を送らず静かに 0 を返す。
     */
    private function isConfigured(): bool
    {
        return !empty($this->publicKey) && !empty($this->privateKey);
    }

    /**
     * Send push notification to a single user (all their subscriptions).
     */
    public function sendToUser(int $userId, string $title, string $body, ?string $url = null): int
    {
        if (!$this->isConfigured()) {
            Log::debug('Web Push skipped: VAPID keys not configured', ['user_id' => $userId]);
            return 0;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'icon' => '/assets/icons/icon-192x192.svg',
            'badge' => '/assets/icons/icon-72x72.svg',
            'url' => $url ?? '/',
        ], JSON_UNESCAPED_UNICODE);

        $sent = 0;
        foreach ($subscriptions as $subscription) {
            $result = $this->sendPush($subscription, $payload);
            if ($result === true) {
                $sent++;
            } elseif ($result === 'gone') {
                // 410 Gone - subscription expired, remove from DB
                $subscription->delete();
                Log::info('Removed expired push subscription', [
                    'user_id' => $userId,
                    'endpoint' => substr($subscription->endpoint, 0, 80),
                ]);
            }
        }

        Log::info('Web Push sent', [
            'user_id' => $userId,
            'sent' => $sent,
            'total_subscriptions' => $subscriptions->count(),
        ]);

        return $sent;
    }

    /**
     * Send push notification to multiple users.
     */
    public function sendToUsers(array $userIds, string $title, string $body, ?string $url = null): int
    {
        $total = 0;
        foreach ($userIds as $userId) {
            $total += $this->sendToUser((int) $userId, $title, $body, $url);
        }

        return $total;
    }

    // ========================================================================
    // Web Push Protocol Implementation
    // ========================================================================

    /**
     * Send an encrypted push message to a single subscription.
     *
     * @return true|string|false  true=success, 'gone'=expired, false=error
     */
    private function sendPush(PushSubscription $subscription, string $payload): bool|string
    {
        if (empty($this->publicKey) || empty($this->privateKey)) {
            Log::warning('Web Push: VAPID keys not configured');

            return false;
        }

        try {
            $encrypted = $this->encryptPayload(
                $payload,
                $subscription->p256dh,
                $subscription->auth
            );

            $parsed = parse_url($subscription->endpoint);
            $audience = $parsed['scheme'] . '://' . $parsed['host'];

            $jwt = $this->createVapidJwt($audience);
            if (! $jwt) {
                Log::error('Web Push: JWT creation failed');

                return false;
            }

            $headers = [
                'TTL' => '86400',
                'Urgency' => 'normal',
                'Authorization' => 'vapid t=' . $jwt . ', k=' . $this->publicKey,
            ];

            if ($encrypted) {
                $headers['Content-Type'] = 'application/octet-stream';
                $headers['Content-Encoding'] = 'aes128gcm';
                $body = $encrypted['ciphertext'];
            } else {
                $headers['Content-Length'] = '0';
                $body = '';
            }

            $response = Http::withHeaders($headers)
                ->withBody($body, $headers['Content-Type'] ?? 'application/octet-stream')
                ->timeout(30)
                ->post($subscription->endpoint);

            $httpCode = $response->status();

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            if ($httpCode === 410 || $httpCode === 404) {
                return 'gone';
            }

            Log::warning('Web Push HTTP error', [
                'status' => $httpCode,
                'body' => substr($response->body(), 0, 200),
                'endpoint' => substr($subscription->endpoint, 0, 80),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Web Push error', [
                'message' => $e->getMessage(),
                'endpoint' => substr($subscription->endpoint, 0, 80),
            ]);

            return false;
        }
    }

    // ========================================================================
    // VAPID JWT (RFC 8292)
    // ========================================================================

    private function createVapidJwt(string $audience): ?string
    {
        $header = $this->base64urlEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = $this->base64urlEncode(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $this->subject,
        ]));

        $signingData = $header . '.' . $payload;

        $privKeyRaw = $this->base64urlDecode($this->privateKey);
        $pubKeyRaw = $this->base64urlDecode($this->publicKey);

        $pem = $this->createEcPemFromRaw($privKeyRaw, $pubKeyRaw);
        $key = openssl_pkey_get_private($pem);
        if (! $key) {
            Log::error('VAPID: Failed to load private key');

            return null;
        }

        openssl_sign($signingData, $signature, $key, OPENSSL_ALGO_SHA256);

        $rawSig = $this->derToRaw($signature);

        return $signingData . '.' . $this->base64urlEncode($rawSig);
    }

    // ========================================================================
    // Message Encryption (RFC 8291 + aes128gcm)
    // ========================================================================

    private function encryptPayload(string $payload, string $userPublicKeyB64, string $userAuthB64): ?array
    {
        $userPublicKey = $this->base64urlDecode($userPublicKeyB64);
        $userAuth = $this->base64urlDecode($userAuthB64);

        // Generate local ECDH key pair
        $localKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if (! $localKey) {
            Log::error('Web Push: Cannot generate EC key pair');

            return null;
        }

        $localDetails = openssl_pkey_get_details($localKey);
        $localPubRaw = chr(4)
            . str_pad($localDetails['ec']['x'], 32, chr(0), STR_PAD_LEFT)
            . str_pad($localDetails['ec']['y'], 32, chr(0), STR_PAD_LEFT);
        $localPrivRaw = str_pad($localDetails['ec']['d'], 32, chr(0), STR_PAD_LEFT);

        // ECDH shared secret
        $sharedSecret = $this->computeEcdh($localPrivRaw, $localPubRaw, $userPublicKey);
        if (! $sharedSecret) {
            return null;
        }

        // Salt for content coding header
        $salt = random_bytes(16);

        // IKM from auth secret
        $authInfo = "WebPush: info\x00" . $userPublicKey . $localPubRaw;
        $ikm = $this->hkdfExpand(
            $this->hkdfExtract($userAuth, $sharedSecret),
            $authInfo,
            32
        );

        // PRK from salt
        $prk = $this->hkdfExtract($salt, $ikm);

        // Derive content encryption key and nonce
        $cekInfo = "Content-Encoding: aes128gcm\x00";
        $nonceInfo = "Content-Encoding: nonce\x00";
        $cek = $this->hkdfExpand($prk, $cekInfo, 16);
        $nonce = $this->hkdfExpand($prk, $nonceInfo, 12);

        // Pad payload (RFC 8188) - delimiter byte
        $paddedPayload = $payload . chr(2);

        // Encrypt with AES-128-GCM
        $tag = '';
        $encrypted = openssl_encrypt(
            $paddedPayload,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($encrypted === false) {
            Log::error('Web Push: AES-GCM encryption failed');

            return null;
        }

        // Build aes128gcm content coding header (RFC 8188)
        // salt (16) + rs (4 uint32) + idlen (1) + keyid (65 = uncompressed public key)
        $rs = pack('N', 4096);
        $idLen = chr(65);
        $header = $salt . $rs . $idLen . $localPubRaw;

        return [
            'ciphertext' => $header . $encrypted . $tag,
            'localPublicKey' => $localPubRaw,
            'salt' => $salt,
        ];
    }

    // ========================================================================
    // ECDH Key Exchange
    // ========================================================================

    private function computeEcdh(string $privKeyRaw, string $localPubKeyRaw, string $peerPubKeyRaw): ?string
    {
        if (! function_exists('openssl_pkey_derive')) {
            Log::error('Web Push: openssl_pkey_derive not available');

            return null;
        }

        // OpenSSL が EC 秘密鍵 PEM を読み込む際、内蔵の公開鍵が曲線上の有効点か
        // どうかを検証するため、encryptPayload() で導出した本物の公開鍵を渡す。
        $localPem = $this->createEcPemFromRaw($privKeyRaw, $localPubKeyRaw);
        $peerPem = $this->createEcPublicPem($peerPubKeyRaw);

        $localKeyRes = openssl_pkey_get_private($localPem);
        $peerKeyRes = openssl_pkey_get_public($peerPem);

        if (! $localKeyRes || ! $peerKeyRes) {
            Log::error('Web Push ECDH: Failed to load keys');

            return null;
        }

        $shared = openssl_pkey_derive($peerKeyRes, $localKeyRes, 256);
        if ($shared === false) {
            Log::error('Web Push ECDH: derive failed');

            return null;
        }

        return $shared;
    }

    // ========================================================================
    // EC Key PEM Construction
    // ========================================================================

    private function createEcPemFromRaw(string $privKey, string $pubKey): string
    {
        $oid = hex2bin('06082a8648ce3d030107'); // prime256v1

        $privOctet = $this->asn1OctetString($privKey);
        $params = chr(0xA0) . $this->asn1Length(strlen($oid)) . $oid;
        $bitString = chr(0x03) . $this->asn1Length(strlen($pubKey) + 1) . chr(0x00) . $pubKey;
        $pubKeyCtx = chr(0xA1) . $this->asn1Length(strlen($bitString)) . $bitString;
        $version = chr(0x02) . chr(0x01) . chr(0x01);

        $ecPrivKey = $version . $privOctet . $params . $pubKeyCtx;
        $ecPrivKeySeq = chr(0x30) . $this->asn1Length(strlen($ecPrivKey)) . $ecPrivKey;

        return "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($ecPrivKeySeq), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";
    }

    private function createEcPublicPem(string $pubKeyRaw): string
    {
        $oidEc = hex2bin('06072a8648ce3d0201');
        $oidP256 = hex2bin('06082a8648ce3d030107');
        $algId = chr(0x30) . $this->asn1Length(strlen($oidEc) + strlen($oidP256)) . $oidEc . $oidP256;

        $bitString = chr(0x03) . $this->asn1Length(strlen($pubKeyRaw) + 1) . chr(0x00) . $pubKeyRaw;

        $spki = chr(0x30) . $this->asn1Length(strlen($algId) + strlen($bitString)) . $algId . $bitString;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    // ========================================================================
    // Crypto Helpers
    // ========================================================================

    private function asn1OctetString(string $data): string
    {
        return chr(0x04) . $this->asn1Length(strlen($data)) . $data;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }
        $bytes = '';
        $temp = $length;
        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function derToRaw(string $der): string
    {
        $pos = 0;
        if (ord($der[$pos]) !== 0x30) {
            return $der;
        }
        $pos += 2;

        if (ord($der[$pos]) !== 0x02) {
            return $der;
        }
        $pos++;
        $rLen = ord($der[$pos]);
        $pos++;
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        if (ord($der[$pos]) !== 0x02) {
            return $der;
        }
        $pos++;
        $sLen = ord($der[$pos]);
        $pos++;
        $s = substr($der, $pos, $sLen);

        $r = str_pad(ltrim($r, chr(0)), 32, chr(0), STR_PAD_LEFT);
        $s = str_pad(ltrim($s, chr(0)), 32, chr(0), STR_PAD_LEFT);

        return $r . $s;
    }

    private function hkdfExtract(string $salt, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $result = '';
        $t = '';
        $counter = 1;
        while (strlen($result) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
            $result .= $t;
            $counter++;
        }

        return substr($result, 0, $length);
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}
