<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer;

use Switch\View\View;

class Mailable
{
    private array $to = [];
    private array $from = [];
    private array $cc = [];
    private array $bcc = [];
    private array $replyTo = [];
    private string $subject = '';
    private ?string $htmlBody = null;
    private ?string $textBody = null;
    private ?string $viewTemplate = null;
    private array $headers = [];

    public string $queueName = 'default';
    public int $queueDelay = 0;
    public int $queueTries = 3;

    public function onQueue(string $queue): static
    {
        $this->queueName = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->queueDelay = $seconds;
        return $this;
    }

    public function tries(int $tries): static
    {
        $this->queueTries = $tries;
        return $this;
    }

    public function shouldQueue(): bool
    {
        return $this instanceof \Switch\Foundation\Queue\ShouldQueue;
    }

    public function queue(): string|int
    {
        $job = new \Switch\Foundation\Mailer\Job\SendQueuedMailableJob($this);
        return \Switch\Foundation\Queue\Facade\Queue::push($job, $this->queueName);
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->addAddress('to', $address, $name);
        return $this;
    }

    public function from(string|array $address, ?string $name = null): static
    {
        $this->addAddress('from', $address, $name);
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->addAddress('cc', $address, $name);
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->addAddress('bcc', $address, $name);
        return $this;
    }

    public function replyTo(string|array $address, ?string $name = null): static
    {
        $this->addAddress('replyTo', $address, $name);
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlBody = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->textBody = $text;
        return $this;
    }

    public function view(string $template, array $data = []): static
    {
        $this->viewTemplate = $template;
        $this->viewData = $data;
        return $this;
    }

    public function attach(string $filePath, ?string $asName = null, ?string $mime = null): static
    {
        $this->attachments[] = [
            'path' => $filePath,
            'name' => $asName ?? basename($filePath),
            'mime' => $mime ?? (function_exists('mime_content_type') ? @mime_content_type($filePath) : 'application/octet-stream'),
        ];
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getFrom(): array
    {
        return $this->from;
    }

    public function getCc(): array
    {
        return $this->cc;
    }

    public function getBcc(): array
    {
        return $this->bcc;
    }

    public function getReplyTo(): array
    {
        return $this->replyTo;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getHtmlBody(): string
    {
        if ($this->htmlBody !== null) {
            return $this->htmlBody;
        }

        if ($this->viewTemplate !== null && class_exists(View::class)) {
            return View::render($this->viewTemplate, $this->viewData);
        }

        return '';
    }

    public function getTextBody(): string
    {
        if ($this->textBody !== null) {
            return $this->textBody;
        }

        $html = $this->getHtmlBody();
        return strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ["\n", "\n", "\n", "\n\n"], $html));
    }

    /**
     * Render complete RFC 2822 email payload.
     */
    public function renderRaw(): string
    {
        $boundary = '=_switch_' . md5((string) microtime(true));
        $altBoundary = '=_switch_alt_' . md5((string) microtime(true));

        $headers = [];
        $from = !empty($this->from) ? $this->formatAddressList($this->from) : 'Switch Framework <noreply@localhost>';
        $headers[] = "From: {$from}";
        $headers[] = "To: " . $this->formatAddressList($this->to);

        if (!empty($this->cc)) {
            $headers[] = "Cc: " . $this->formatAddressList($this->cc);
        }
        if (!empty($this->replyTo)) {
            $headers[] = "Reply-To: " . $this->formatAddressList($this->replyTo);
        }

        $headers[] = "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Date: " . date('r');

        foreach ($this->headers as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $hasAttachments = !empty($this->attachments);

        if ($hasAttachments) {
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
            $body = "--{$boundary}\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
        } else {
            $headers[] = "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"";
            $body = "";
        }

        // Text Part
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->getTextBody())) . "\r\n";

        // HTML Part
        $body .= "--{$altBoundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($this->getHtmlBody())) . "\r\n";
        $body .= "--{$altBoundary}--\r\n";

        // Attachments Part
        if ($hasAttachments) {
            foreach ($this->attachments as $att) {
                if (file_exists($att['path'])) {
                    $contents = @file_get_contents($att['path']);
                    if ($contents !== false) {
                        $body .= "\r\n--{$boundary}\r\n";
                        $body .= "Content-Type: {$att['mime']}; name=\"{$att['name']}\"\r\n";
                        $body .= "Content-Transfer-Encoding: base64\r\n";
                        $body .= "Content-Disposition: attachment; filename=\"{$att['name']}\"\r\n\r\n";
                        $body .= chunk_split(base64_encode($contents));
                    }
                }
            }
            $body .= "\r\n--{$boundary}--\r\n";
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function addAddress(string $type, string|array $address, ?string $name = null): void
    {
        if (is_array($address)) {
            foreach ($address as $email => $n) {
                if (is_numeric($email)) {
                    $this->{$type}[] = ['email' => $n, 'name' => null];
                } else {
                    $this->{$type}[] = ['email' => $email, 'name' => $n];
                }
            }
        } else {
            $this->{$type}[] = ['email' => $address, 'name' => $name];
        }
    }

    private function formatAddressList(array $list): string
    {
        $formatted = [];
        foreach ($list as $item) {
            if (!empty($item['name'])) {
                $formatted[] = '"' . addslashes($item['name']) . '" <' . $item['email'] . '>';
            } else {
                $formatted[] = '<' . $item['email'] . '>';
            }
        }
        return implode(', ', $formatted);
    }
}
