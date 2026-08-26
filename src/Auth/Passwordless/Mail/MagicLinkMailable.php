<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless\Mail;

use Switch\Foundation\Mailer\Mailable;

class MagicLinkMailable extends Mailable
{
    private string $verifyUrl;
    private string $type;
    private int $expiresInMinutes;
    private string $appName;

    public function __construct(
        string $verifyUrl,
        string $type = 'login',
        int $expiresInMinutes = 15,
        string $appName = 'Switch Framework'
    ) {
        $this->verifyUrl = $verifyUrl;
        $this->type = $type;
        $this->expiresInMinutes = $expiresInMinutes;
        $this->appName = $appName;

        $subject = match ($type) {
            'register' => "Confirm Your Registration — {$appName}",
            'recovery' => "Account Recovery — {$appName}",
            default => "Your Magic Login Link — {$appName}",
        };

        $this->subject($subject);
        $this->html($this->generateHtml());
        $this->text($this->generateText());
    }

    public function getVerifyUrl(): string
    {
        return $this->verifyUrl;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getExpiresInMinutes(): int
    {
        return $this->expiresInMinutes;
    }

    private function generateHtml(): string
    {
        $actionTitle = match ($this->type) {
            'register' => 'Complete Your Registration',
            'recovery' => 'Recover Your Account',
            default => 'Log In to Your Account',
        };

        $actionDesc = match ($this->type) {
            'register' => 'Click the button below to confirm your email and create your account.',
            'recovery' => 'Click the button below to verify your email and recover access to your account.',
            default => 'Click the button below to instantly sign in without a password.',
        };

        $buttonText = match ($this->type) {
            'register' => 'Confirm & Create Account',
            'recovery' => 'Recover Account Access',
            default => 'Sign In to ' . htmlspecialchars($this->appName),
        };

        $url = htmlspecialchars($this->verifyUrl, ENT_QUOTES, 'UTF-8');
        $appName = htmlspecialchars($this->appName, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$actionTitle}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #0f172a; margin: 0; padding: 40px 20px; color: #f8fafc;">
    <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width: 560px; background-color: #1e293b; border-radius: 16px; border: 1px solid rgba(255,255,255,0.08); padding: 36px; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
                    <tr>
                        <td align="center" style="padding-bottom: 24px;">
                            <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #38bdf8; letter-spacing: -0.5px;">{$appName}</h2>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <h1 style="margin: 0 0 16px; font-size: 20px; font-weight: 600; color: #ffffff; text-align: center;">{$actionTitle}</h1>
                            <p style="margin: 0 0 28px; font-size: 15px; line-height: 1.6; color: #94a3b8; text-align: center;">{$actionDesc}</p>
                            
                            <table role="presentation" width="100%" border="0" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0 30px;">
                                        <a href="{$url}" target="_blank" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #06b6d4, #2563eb); color: #ffffff; text-decoration: none; font-weight: 600; font-size: 15px; border-radius: 10px; box-shadow: 0 4px 20px rgba(6, 182, 212, 0.4);">
                                            {$buttonText}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 16px; font-size: 13px; color: #64748b; text-align: center;">
                                This magic link expires in <strong>{$this->expiresInMinutes} minutes</strong> and can only be used once.
                            </p>

                            <hr style="border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 24px 0;">

                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; word-break: break-all;">
                                If you're having trouble clicking the button, copy and paste this URL into your browser:<br>
                                <a href="{$url}" style="color: #38bdf8; text-decoration: none;">{$url}</a>
                            </p>
                            
                            <p style="margin: 16px 0 0; font-size: 12px; color: #475569; text-align: center;">
                                If you did not request this email, no action is needed. You can safely ignore it.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    private function generateText(): string
    {
        $actionTitle = match ($this->type) {
            'register' => 'Complete Your Registration',
            'recovery' => 'Recover Your Account',
            default => 'Log In to Your Account',
        };

        return <<<TEXT
{$this->appName} - {$actionTitle}

Use the link below to continue:
{$this->verifyUrl}

This link expires in {$this->expiresInMinutes} minutes and can only be used once.

If you did not request this link, you can safely ignore this email.
TEXT;
    }
}
