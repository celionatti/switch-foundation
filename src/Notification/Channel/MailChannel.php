<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Channel;

use InvalidArgumentException;
use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Notification\Notification;

class MailChannel implements ChannelInterface
{
    private ?MailManager $mailer;

    public function __construct(?MailManager $mailer = null)
    {
        $this->mailer = $mailer;
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        $recipient = $this->getRecipientAddress($notifiable);
        if (!$recipient) {
            return;
        }

        $mailer = $this->mailer ?? MailManager::getInstance();
        $message = $notification->toMail($notifiable);

        if ($message instanceof Mailable) {
            $message->to($recipient);
            $mailer->send($message);
        } elseif (is_string($message)) {
            $mailable = (new Mailable())
                ->to($recipient)
                ->subject('New Notification')
                ->html($message);
            $mailer->send($mailable);
        }
    }

    protected function getRecipientAddress(mixed $notifiable): ?string
    {
        if (is_string($notifiable)) {
            return $notifiable;
        }

        if (is_object($notifiable) && method_exists($notifiable, 'routeNotificationFor')) {
            $route = $notifiable->routeNotificationFor('mail');
            if ($route) {
                return (string) $route;
            }
        }

        if (is_object($notifiable) && isset($notifiable->email)) {
            return (string) $notifiable->email;
        }

        return null;
    }
}
