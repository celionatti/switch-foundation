<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Transport;

use Switch\Foundation\Mailer\Mailable;

class ArrayTransport implements TransportInterface
{
    private array $messages = [];

    public function send(Mailable $mailable): bool
    {
        $this->messages[] = $mailable;
        return true;
    }

    /**
     * @return array<int, Mailable>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function last(): ?Mailable
    {
        return end($this->messages) ?: null;
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function flush(): void
    {
        $this->messages = [];
    }
}
