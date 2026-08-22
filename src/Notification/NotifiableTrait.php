<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification;

use PDO;
use Switch\Database\DB;
use Switch\Foundation\Notification\Facade\Notification as NotificationFacade;

trait NotifiableTrait
{
    public function notify(Notification $notification): void
    {
        NotificationFacade::send($this, $notification);
    }

    public function notifyNow(Notification $notification): void
    {
        NotificationFacade::sendNow($this, $notification);
    }

    public function routeNotificationFor(string $channel): mixed
    {
        return match ($channel) {
            'mail' => $this->email ?? $this->mail ?? null,
            default => null,
        };
    }

    /**
     * @return array<int, DatabaseNotification>
     */
    public function notifications(): array
    {
        return $this->queryNotifications();
    }

    /**
     * @return array<int, DatabaseNotification>
     */
    public function unreadNotifications(): array
    {
        return $this->queryNotifications(unreadOnly: true);
    }

    /**
     * @return array<int, DatabaseNotification>
     */
    public function readNotifications(): array
    {
        return $this->queryNotifications(readOnly: true);
    }

    public function markAllNotificationsAsRead(): void
    {
        $pdo = $this->getPdoForNotification();
        if ($pdo === null) {
            return;
        }

        $stmt = $pdo->prepare(
            "UPDATE `notifications` SET `read_at` = :now
             WHERE `notifiable_type` = :type AND `notifiable_id` = :id AND `read_at` IS NULL"
        );
        $stmt->execute([
            ':now' => date('Y-m-d H:i:s'),
            ':type' => static::class,
            ':id' => method_exists($this, 'getAuthIdentifier') ? $this->getAuthIdentifier() : ($this->id ?? null),
        ]);
    }

    protected function queryNotifications(bool $unreadOnly = false, bool $readOnly = false): array
    {
        $pdo = $this->getPdoForNotification();
        if ($pdo === null) {
            return [];
        }

        $id = method_exists($this, 'getAuthIdentifier') ? $this->getAuthIdentifier() : ($this->id ?? null);
        if ($id === null) {
            return [];
        }

        $sql = "SELECT * FROM `notifications` WHERE `notifiable_type` = :type AND `notifiable_id` = :id";
        if ($unreadOnly) {
            $sql .= " AND `read_at` IS NULL";
        } elseif ($readOnly) {
            $sql .= " AND `read_at` IS NOT NULL";
        }
        $sql .= " ORDER BY `created_at` DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':type' => static::class,
            ':id' => $id,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[] = new DatabaseNotification($row);
        }

        return $results;
    }

    private function getPdoForNotification(): ?PDO
    {
        if (property_exists($this, 'pdo') && $this->pdo instanceof PDO) {
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
