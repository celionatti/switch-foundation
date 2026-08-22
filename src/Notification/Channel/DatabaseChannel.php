<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Channel;

use PDO;
use Switch\Database\DB;
use Switch\Foundation\Notification\Notification;

class DatabaseChannel implements ChannelInterface
{
    private ?PDO $pdo;
    private string $table;

    public function __construct(?PDO $pdo = null, string $table = 'notifications')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return;
        }

        $data = $notification->toDatabase($notifiable);
        $notifiableType = is_object($notifiable) ? get_class($notifiable) : 'anonymous';
        $notifiableId = method_exists($notifiable, 'getAuthIdentifier')
            ? $notifiable->getAuthIdentifier()
            : ($notifiable->id ?? 0);

        $stmt = $pdo->prepare(
            "INSERT INTO `{$this->table}` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`)
             VALUES (:id, :type, :notifiable_type, :notifiable_id, :data, NULL, :created_at)"
        );

        $stmt->execute([
            ':id' => $notification->id,
            ':type' => get_class($notification),
            ':notifiable_type' => $notifiableType,
            ':notifiable_id' => $notifiableId,
            ':data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function getPdo(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (class_exists(DB::class)) {
            try {
                return DB::getPdo();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
