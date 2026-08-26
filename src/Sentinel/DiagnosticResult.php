<?php

declare(strict_types=1);

namespace Switch\Foundation\Sentinel;

class DiagnosticResult
{
    public const LEVEL_CRITICAL = 'CRITICAL';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_PASS = 'PASS';

    public function __construct(
        public readonly string $level,
        public readonly string $category,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $remediation = null,
        public readonly array $metadata = []
    ) {
    }

    public static function critical(string $category, string $title, string $description, ?string $remediation = null, array $metadata = []): self
    {
        return new self(self::LEVEL_CRITICAL, $category, $title, $description, $remediation, $metadata);
    }

    public static function warning(string $category, string $title, string $description, ?string $remediation = null, array $metadata = []): self
    {
        return new self(self::LEVEL_WARNING, $category, $title, $description, $remediation, $metadata);
    }

    public static function info(string $category, string $title, string $description, ?string $remediation = null, array $metadata = []): self
    {
        return new self(self::LEVEL_INFO, $category, $title, $description, $remediation, $metadata);
    }

    public static function pass(string $category, string $title, string $description, array $metadata = []): self
    {
        return new self(self::LEVEL_PASS, $category, $title, $description, null, $metadata);
    }

    public function isCritical(): bool
    {
        return $this->level === self::LEVEL_CRITICAL;
    }

    public function isWarning(): bool
    {
        return $this->level === self::LEVEL_WARNING;
    }

    public function isIssue(): bool
    {
        return $this->level === self::LEVEL_CRITICAL || $this->level === self::LEVEL_WARNING;
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'remediation' => $this->remediation,
            'metadata' => $this->metadata,
        ];
    }
}
