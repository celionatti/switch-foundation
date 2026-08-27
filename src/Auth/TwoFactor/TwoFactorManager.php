<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\TwoFactor;

class TwoFactorManager
{
    private static ?self $instance = null;

    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Generate a cryptographically secure Base32 secret key.
     */
    public function generateSecretKey(int $length = 16): string
    {
        $secret = '';
        $max = strlen(self::BASE32_CHARS) - 1;

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $max)];
        }

        return $secret;
    }

    /**
     * Calculate current 6-digit TOTP code for a given secret at a timestamp.
     */
    public function getCode(string $secret, ?int $timestamp = null, int $timeSlice = 30): string
    {
        $time = $timestamp ?? time();
        $timeCounter = (int) floor($time / $timeSlice);

        return $this->generateOtp($secret, $timeCounter);
    }

    /**
     * Verify a 6-digit TOTP code against a secret key with clock drift tolerance.
     *
     * @param string $secret
     * @param string $code
     * @param int $window Number of 30-second windows before/after to accept (default: 1)
     * @param int|null $timestamp
     * @return bool
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $time = $timestamp ?? time();
        $currentTimeCounter = (int) floor($time / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculated = $this->generateOtp($secret, $currentTimeCounter + $i);
            if (hash_equals($calculated, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate an otpauth:// URL for QR code generation.
     */
    public function getQrCodeUrl(string $company, string $account, string $secret): string
    {
        $issuer = rawurlencode($company);
        $label = rawurlencode($company . ':' . $account);

        return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Generate a list of single-use emergency recovery backup codes.
     *
     * @return array<int, string> e.g. ['ABCD-EF12', '3456-7890', ...]
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }

        return $codes;
    }

    /**
     * Generate OTP code using HMAC-SHA1 counter.
     */
    private function generateOtp(string $secret, int $counter): string
    {
        $secretBytes = $this->base32Decode($secret);

        // Pack counter into 8-byte big-endian binary
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);

        $hash = hash_hmac('sha1', $binaryCounter, $secretBytes, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $binaryCode = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binaryCode % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode a secret key into raw binary bytes.
     */
    private function base32Decode(string $b32): string
    {
        $b32 = strtoupper(trim($b32));
        $buffer = 0;
        $bufferBits = 0;
        $binary = '';

        for ($i = 0; $i < strlen($b32); $i++) {
            $char = $b32[$i];
            $val = strpos(self::BASE32_CHARS, $char);
            if ($val === false) {
                continue; // Ignore padding or invalid characters
            }

            $buffer = ($buffer << 5) | $val;
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $binary .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $binary;
    }
}
