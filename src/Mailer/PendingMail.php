<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer;

class PendingMail
{
    private MailManager $manager;
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];

    public function __construct(MailManager $manager)
    {
        $this->manager = $manager;
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->cc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->bcc[] = ['address' => $address, 'name' => $name];
        return $this;
    }

    public function send(Mailable $mailable): bool|string|int
    {
        $this->fillRecipients($mailable);
        return $this->manager->send($mailable);
    }

    public function sendNow(Mailable $mailable): bool
    {
        $this->fillRecipients($mailable);
        return $this->manager->sendNow($mailable);
    }

    public function queue(Mailable $mailable): string|int
    {
        $this->fillRecipients($mailable);
        return $this->manager->queue($mailable);
    }

    private function fillRecipients(Mailable $mailable): void
    {
        foreach ($this->to as $recipient) {
            $mailable->to($recipient['address'], $recipient['name']);
        }
        foreach ($this->cc as $recipient) {
            $mailable->cc($recipient['address'], $recipient['name']);
        }
        foreach ($this->bcc as $recipient) {
            $mailable->bcc($recipient['address'], $recipient['name']);
        }
    }
}
