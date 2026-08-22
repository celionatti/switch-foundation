<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer;

use InvalidArgumentException;
use Switch\Foundation\Mailer\Transport\ArrayTransport;
use Switch\Foundation\Mailer\Transport\LogTransport;
use Switch\Foundation\Mailer\Transport\SendmailTransport;
use Switch\Foundation\Mailer\Transport\SmtpTransport;
use Switch\Foundation\Mailer\Transport\TransportInterface;

class MailManager
{
    private static ?self $instance = null;
    private array $config;
    private array $transports = [];
    private array $customTransports = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'log',
            'mailers' => [
                'smtp' => [
                    'transport' => 'smtp',
                    'host' => '127.0.0.1',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => null,
                    'password' => null,
                ],
                'sendmail' => [
                    'transport' => 'sendmail',
                    'path' => '/usr/sbin/sendmail -bs',
                ],
                'log' => [
                    'transport' => 'log',
                    'path' => 'storage/logs/mail.log',
                ],
                'array' => [
                    'transport' => 'array',
                ],
            ],
            'from' => [
                'address' => 'hello@example.com',
                'name' => 'Switch Framework',
            ],
        ], $config);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function mailer(?string $name = null): TransportInterface
    {
        $name ??= $this->getDefaultMailer();

        if (isset($this->transports[$name])) {
            return $this->transports[$name];
        }

        return $this->transports[$name] = $this->resolve($name);
    }

    public function setTransport(string $name, TransportInterface $transport): static
    {
        $this->transports[$name] = $transport;
        return $this;
    }

    public function extend(string $driver, callable $callback): static
    {
        $this->customTransports[$driver] = $callback;
        return $this;
    }

    public function getDefaultMailer(): string
    {
        return $this->config['default'] ?? 'log';
    }

    public function setDefaultMailer(string $name): void
    {
        $this->config['default'] = $name;
    }

    public function to(string|array $address, ?string $name = null): PendingMail
    {
        return (new PendingMail($this))->to($address, $name);
    }

    public function send(Mailable $mailable): bool|string|int
    {
        // Apply default From address if not set
        if (empty($mailable->getFrom()) && !empty($this->config['from']['address'])) {
            $mailable->from($this->config['from']['address'], $this->config['from']['name'] ?? null);
        }

        if ($mailable->shouldQueue()) {
            return $this->queue($mailable);
        }

        return $this->sendNow($mailable);
    }

    public function sendNow(Mailable $mailable): bool
    {
        if (empty($mailable->getFrom()) && !empty($this->config['from']['address'])) {
            $mailable->from($this->config['from']['address'], $this->config['from']['name'] ?? null);
        }

        return $this->mailer()->send($mailable);
    }

    public function queue(Mailable $mailable): string|int
    {
        if (empty($mailable->getFrom()) && !empty($this->config['from']['address'])) {
            $mailable->from($this->config['from']['address'], $this->config['from']['name'] ?? null);
        }

        $job = new \Switch\Foundation\Mailer\Job\SendQueuedMailableJob($mailable);
        return \Switch\Foundation\Queue\Facade\Queue::push($job, $mailable->queueName);
    }

    public function raw(string $text, callable $callback): bool
    {
        $mailable = new Mailable();
        $mailable->text($text);
        $callback($mailable);
        return $this->send($mailable);
    }

    private function resolve(string $name): TransportInterface
    {
        $config = $this->config['mailers'][$name] ?? ['transport' => $name];
        $transport = $config['transport'] ?? $name;

        if (isset($this->customTransports[$transport])) {
            return ($this->customTransports[$transport])($this, $config);
        }

        return match ($transport) {
            'smtp' => new SmtpTransport(
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 587),
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['encryption'] ?? 'tls',
                (int) ($config['timeout'] ?? 10)
            ),
            'sendmail' => new SendmailTransport($config['path'] ?? '/usr/sbin/sendmail -bs'),
            'log' => new LogTransport($config['path'] ?? 'storage/logs/mail.log'),
            'array' => new ArrayTransport(),
            default => throw new InvalidArgumentException("Mail transport [{$transport}] is not supported."),
        };
    }
}
