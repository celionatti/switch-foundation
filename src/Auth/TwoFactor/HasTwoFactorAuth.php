<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\TwoFactor;

use Switch\Foundation\Auth\TwoFactor\Facade\TwoFactor;

trait HasTwoFactorAuth
{
    /**
     * Determine if Two-Factor Authentication is confirmed and active.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return !empty($this->two_factor_secret) && !empty($this->two_factor_confirmed_at);
    }

    /**
     * Enable Two-Factor Auth for this model with a fresh secret & recovery codes.
     *
     * @return array{secret: string, qr_code_url: string, recovery_codes: array<int, string>}
     */
    public function enableTwoFactor(string $appName = 'Switch'): array
    {
        $secret = TwoFactor::generateSecretKey(16);
        $recoveryCodes = TwoFactor::generateRecoveryCodes(8);

        $this->two_factor_secret = $secret;
        $this->two_factor_recovery_codes = json_encode(array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $recoveryCodes));
        $this->two_factor_confirmed_at = null; // Awaiting first verification

        if (method_exists($this, 'save')) {
            $this->save();
        }

        $email = $this->email ?? (string) $this->id;
        $qrCodeUrl = TwoFactor::getQrCodeUrl($appName, $email, $secret);

        return [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Confirm Two-Factor Auth setup using a 6-digit code.
     */
    public function confirmTwoFactor(string $code): bool
    {
        if (empty($this->two_factor_secret)) {
            return false;
        }

        if (TwoFactor::verify($this->two_factor_secret, $code)) {
            $this->two_factor_confirmed_at = date('Y-m-d H:i:s');
            if (method_exists($this, 'save')) {
                $this->save();
            }
            return true;
        }

        return false;
    }

    /**
     * Verify a 6-digit TOTP code.
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (!$this->hasTwoFactorEnabled()) {
            return true; // Not enabled
        }

        return TwoFactor::verify($this->two_factor_secret, $code);
    }

    /**
     * Consume a single-use emergency recovery backup code.
     */
    public function consumeRecoveryCode(string $code): bool
    {
        if (empty($this->two_factor_recovery_codes)) {
            return false;
        }

        $rawCodes = json_decode((string) $this->two_factor_recovery_codes, true) ?: [];
        $code = trim($code);

        foreach ($rawCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                unset($rawCodes[$index]);
                $this->two_factor_recovery_codes = json_encode(array_values($rawCodes));
                if (method_exists($this, 'save')) {
                    $this->save();
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Disable Two-Factor Auth.
     */
    public function disableTwoFactor(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_recovery_codes = null;
        $this->two_factor_confirmed_at = null;

        if (method_exists($this, 'save')) {
            $this->save();
        }
    }
}
