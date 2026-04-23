<?php

namespace Tests\Unit;

use App\Services\WebPushService;
use Tests\TestCase;

/**
 * NT012: Web Push 暗号化の単体テスト
 *
 * 差分カテゴリ: api
 * 目的: encryptPayload() が ECDH + aes128gcm で実際に暗号化ペイロードを返すことを確認する。
 * 既存バグ: computeEcdh() が createEcPemFromRaw にダミー公開鍵を渡しているため
 *           openssl_pkey_get_private() が失敗し、sendPush が常に false になっていた。
 */
class WebPushEncryptionTest extends TestCase
{
    /**
     * Reflection 経由で private encryptPayload を呼び出す。
     * 購読側 (ブラウザ) の鍵ペアを本物の prime256v1 で生成して渡す。
     */
    public function test_encrypt_payload_returns_ciphertext_for_valid_subscription_keys(): void
    {
        if (! function_exists('openssl_pkey_derive')) {
            $this->markTestSkipped('openssl_pkey_derive not available');
        }

        // 購読者側 (ブラウザ) の鍵ペアを生成
        $peerKey = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        $this->assertNotFalse($peerKey, 'peer EC key generation failed');
        $peerDetails = openssl_pkey_get_details($peerKey);
        $peerPubRaw = chr(4)
            . str_pad($peerDetails['ec']['x'], 32, chr(0), STR_PAD_LEFT)
            . str_pad($peerDetails['ec']['y'], 32, chr(0), STR_PAD_LEFT);

        $peerPubB64 = rtrim(strtr(base64_encode($peerPubRaw), '+/', '-_'), '=');
        $authB64 = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

        $service = new WebPushService();
        $ref = new \ReflectionMethod($service, 'encryptPayload');
        $ref->setAccessible(true);

        $result = $ref->invoke($service, '{"title":"t","body":"b"}', $peerPubB64, $authB64);

        $this->assertIsArray($result, 'encryptPayload returned null — ECDH or AES-GCM failed');
        $this->assertArrayHasKey('ciphertext', $result);
        $this->assertNotEmpty($result['ciphertext']);
        $this->assertArrayHasKey('localPublicKey', $result);
        $this->assertSame(65, strlen($result['localPublicKey']), 'local public key must be 65 bytes (uncompressed P-256)');
        $this->assertArrayHasKey('salt', $result);
        $this->assertSame(16, strlen($result['salt']));
    }
}
