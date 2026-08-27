<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Auth\TwoFactor\Facade\TwoFactor;
use Switch\Foundation\Auth\TwoFactor\HasTwoFactorAuth;
use Switch\Foundation\Auth\TwoFactor\TwoFactorManager;

class DummyUserWith2FA
{
    use HasTwoFactorAuth;

    public int $id = 1;
    public string $email = 'user@example.com';
    public ?string $two_factor_secret = null;
    public ?string $two_factor_recovery_codes = null;
    public ?string $two_factor_confirmed_at = null;

    public function save(): void
    {
        // Mock save
    }
}

class TwoFactorTest extends TestCase
{
    public function testSecretKeyGeneration(): void
    {
        $secret = TwoFactor::generateSecretKey(16);
        $this->assertEquals(16, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testTotpCodeGenerationAndVerification(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP'; // Base32 test secret
        $now = 1700000000; // Fixed timestamp

        $code = TwoFactor::getCode($secret, $now);
        $this->assertEquals(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Verification at exact timestamp
        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now));

        // Verification within 30s drift window
        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now + 25));
        $this->assertTrue(TwoFactor::verify($secret, $code, 1, $now - 25));

        // False on invalid code or out of window
        $this->assertFalse(TwoFactor::verify($secret, '000000', 1, $now));
        $this->assertFalse(TwoFactor::verify($secret, $code, 0, $now + 120));
    }

    public function testQrCodeUrlGeneration(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $url = TwoFactor::getQrCodeUrl('SwitchApp', 'alex@example.com', $secret);

        $this->assertStringStartsWith('otpauth://totp/', $url);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $url);
        $this->assertStringContainsString('issuer=SwitchApp', $url);
    }

    public function testRecoveryCodesLifecycle(): void
    {
        $codes = TwoFactor::generateRecoveryCodes(8);
        $this->assertCount(8, $codes);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{4}-[A-F0-9]{4}$/', $codes[0]);
    }

    public function testModelTraitIntegration(): void
    {
        $user = new DummyUserWith2FA();
        $this->assertFalse($user->hasTwoFactorEnabled());

        $setup = $user->enableTwoFactor('TestApp');
        $this->assertNotEmpty($setup['secret']);
        $this->assertNotEmpty($setup['qr_code_url']);
        $this->assertCount(8, $setup['recovery_codes']);

        // Generate correct code to confirm
        $code = TwoFactor::getCode($setup['secret']);
        $this->assertTrue($user->confirmTwoFactor($code));
        $this->assertTrue($user->hasTwoFactorEnabled());

        // Verify valid code
        $this->assertTrue($user->verifyTwoFactorCode($code));

        // Consume recovery code
        $recoveryCode = $setup['recovery_codes'][0];
        $this->assertTrue($user->consumeRecoveryCode($recoveryCode));
        // Single-use: cannot consume same recovery code twice
        $this->assertFalse($user->consumeRecoveryCode($recoveryCode));

        // Disable 2FA
        $user->disableTwoFactor();
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    public function testGlobalTwoFactorHelper(): void
    {
        $manager = two_factor();
        $this->assertInstanceOf(TwoFactorManager::class, $manager);
    }
}
