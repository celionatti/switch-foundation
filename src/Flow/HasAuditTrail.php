<?php

declare(strict_types=1);

namespace Switch\Foundation\Flow;

trait HasAuditTrail
{
    /**
     * Record an audit event for this model.
     *
     * @param string $event
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @return array<string, mixed>
     */
    public function recordAudit(
        string $event,
        array $meta = [],
        array $oldValues = [],
        array $newValues = []
    ): array {
        return AuditTrail::log($this, $event, $oldValues, $newValues, $meta);
    }

    /**
     * Get audit log entries for this model.
     *
     * @return array<int, array<string, mixed>>
     */
    public function audits(): array
    {
        return AuditTrail::for($this);
    }

    /**
     * Get chronological audit history.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        return $this->audits();
    }
}
