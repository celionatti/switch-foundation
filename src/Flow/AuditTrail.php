<?php

declare(strict_types=1);

namespace Switch\Foundation\Flow;

use Switch\Foundation\Auth\Facade\Auth;

class AuditTrail
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private static array $inMemoryLogs = [];

    /**
     * Record an audit event.
     *
     * @param object $auditable
     * @param string $event
     * @param array<string, mixed> $oldValues
     * @param array<string, mixed> $newValues
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function log(
        object $auditable,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        array $meta = []
    ): array {
        $userId = null;
        if (class_exists(Auth::class)) {
            try {
                $userId = Auth::id();
            } catch (\Throwable) {
                // Ignore if auth not active
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown';

        $entry = [
            'id' => uniqid('audit_', true),
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id ?? ($auditable->getAttribute('id') ?? null),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $userId,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'meta' => $meta,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::$inMemoryLogs[] = $entry;

        return $entry;
    }

    /**
     * Retrieve audit entries for a specific model instance.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function for(object $auditable): array
    {
        $type = get_class($auditable);
        $id = $auditable->id ?? ($auditable->getAttribute('id') ?? null);

        return array_values(array_filter(self::$inMemoryLogs, function ($entry) use ($type, $id) {
            if ($entry['auditable_type'] !== $type) {
                return false;
            }
            if ($id !== null && $entry['auditable_id'] !== $id) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Clear recorded in-memory audits.
     */
    public static function clear(): void
    {
        self::$inMemoryLogs = [];
    }
}
