<?php

namespace App\Services;

/**
 * 自己完結型 TOTP (RFC 6238) 実装。外部 composer 依存を避けるため自前で実装。
 *
 * - HMAC-SHA1、30 秒ステップ、6 桁。Google Authenticator / Authy /
 *   Microsoft Authenticator など標準的な認証アプリと互換。
 * - 時計ズレ対策として前後 1 ステップ (±30 秒) を許容して検証する。
 *
 * セキュリティ上の注意:
 *  - secret は呼び出し側で暗号化保存すること (User モデルの encrypted cast)。
 *  - 検証は constant-time 比較 (hash_equals) を使う。
 */
class TotpService
{
    private const DIGITS = 6;
    private const PERIOD = 30;
    private const ALGO = 'sha1';
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * 新しいランダムな base32 シークレットを生成する (既定 160bit = 32 文字)。
     */
    public function generateSecret(int $bytes = 20): string
    {
        $random = random_bytes($bytes);
        return $this->base32Encode($random);
    }

    /**
     * authenticator アプリ登録用の otpauth:// URI を組み立てる。
     * 手動キー入力もできるよう、呼び出し側で secret 自体も表示すること。
     */
    public function otpauthUri(string $secret, string $accountLabel, string $issuer = 'kiduri'): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($accountLabel);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * 指定時刻 (既定: 現在) の TOTP コードを生成する。
     */
    public function codeAt(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);
        return $this->hotp($secret, $counter);
    }

    /**
     * 入力コードを検証する。時計ズレを考慮して前後 $window ステップを許容。
     */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $current = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $this->hotp($secret, $current + $i);
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * リカバリコードを生成する (既定 8 個、xxxx-xxxx 形式)。
     * @return array<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $a = strtoupper(bin2hex(random_bytes(2)));
            $b = strtoupper(bin2hex(random_bytes(2)));
            $codes[] = "{$a}-{$b}";
        }
        return $codes;
    }

    // ------------------------------------------------------------------
    // 内部実装
    // ------------------------------------------------------------------

    private function hotp(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binCounter = pack('N*', 0) . pack('N*', $counter); // 8-byte big-endian
        $hash = hash_hmac(self::ALGO, $binCounter, $key, true);

        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $part = substr($hash, $offset, 4);
        $value = unpack('N', $part)[1] & 0x7FFFFFFF;
        $otp = $value % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $result .= self::BASE32_ALPHABET[bindec($chunk)];
        }
        return $result;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        if ($secret === '') return '';
        $bits = '';
        foreach (str_split($secret) as $char) {
            $idx = strpos(self::BASE32_ALPHABET, $char);
            if ($idx === false) continue;
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $bytes .= chr(bindec($byte));
            }
        }
        return $bytes;
    }
}
