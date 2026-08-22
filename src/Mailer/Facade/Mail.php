<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Facade;

use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Mailer\PendingMail;
use Switch\Foundation\Mailer\Transport\TransportInterface;

/**
 * Static Mail Facade.
 *
 * @method static TransportInterface mailer(?string $name = null)
 * @method static PendingMail to(string|array $address, ?string $name = null)
 * @method static bool send(Mailable $mailable)
 * @method static bool raw(string $text, callable $callback)
 */
class Mail
{
    public static function mailer(?string $name = null): TransportInterface
    {
        return MailManager::getInstance()->mailer($name);
    }

    public static function to(string|array $address, ?string $name = null): PendingMail
    {
        return MailManager::getInstance()->to($address, $name);
    }

    public static function send(Mailable $mailable): bool
    {
        return MailManager::getInstance()->send($mailable);
    }

    public static function raw(string $text, callable $callback): bool
    {
        return MailManager::getInstance()->raw($text, $callback);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return MailManager::getInstance()->$method(...$arguments);
    }
}
