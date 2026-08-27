<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\TwoFactor\Facade;

use Switch\Foundation\Auth\TwoFactor\TwoFactorManager;

class TwoFactor
{
    public static function getManager(): TwoFactorManager
    {
        return TwoFactorManager::getInstance();
    }

    public static function generateSecretKey(int $length = 16): string
    {
        return self::getManager()->generateSecretKey($length);
    }

    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        return self::getManager()->getCode($secret, $timestamp);
    }

    public static function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        return self::getManager()->verify($secret, $code, $window, $timestamp);
    }

    public static function getQrCodeUrl(string $company, string $account, string $secret): string
    {
        return self::getManager()->getQrCodeUrl($company, $account, $secret);
    }

    public static function generateRecoveryCodes(int $count = 8): array
    {
        return self::getManager()->generateRecoveryCodes($count);
    }
}
