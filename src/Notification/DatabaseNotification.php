<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification;

use JsonSerializable;
use PDO;
use Switch\Database\DB;

class DatabaseNotification implements JsonSerializable
{
    public string $id;
    public string $type;
    public string $notifiableType;
    public mixed $notifiableId;
    public array $data;
    public ?string $readAt;
    public string $createdAt;

    public function __construct(array $attributes = [])
    {
        $this->id = (string) ($attributes['id'] ?? uniqid('notif_', true));
        $this->type = (string) ($attributes['type'] ?? '');
        $this->notifiableType = (string) ($attributes['notifiable_type'] ?? '');
        $this->notifiableId = $attributes['notifiable_id'] ?? null;
        $this->data = is_string($attributes['data'] ?? null)
            ? (json_decode($attributes['data'], true) ?: [])
            : (array) ($attributes['data'] ?? []);
        $this->readAt = isset($attributes['read_at']) ? (string) $attributes['read_at'] : null;
        $this->createdAt = (string) ($attributes['created_at'] ?? date('Y-m-d H:i:s'));
    }

    public function read(): bool
    {
        return $this->readAt !== null;
    }

    public function unread(): bool
    {
        return $this->readAt === null;
    }

    public function markAsRead(): void
    {
        if ($this->unread()) {
            $this->readAt = date('Y-m-d H:i:s');
            $this->updateDatabase(['read_at' => $this->readAt]);
        }
    }

    public function markAsUnread(): void
    {
        if ($this->read()) {
            $this->readAt = null;
            $this->updateDatabase(['read_at' => null]);
        }
    }

    public function delete(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM `notifications` WHERE `id` = :id");
        return $stmt->execute([':id' => $this->id]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'notifiable_type' => $this->notifiableType,
            'notifiable_id' => $this->notifiableId,
            'data' => $this->data,
            'read_at' => $this->readAt,
            'read' => $this->read(),
            'created_at' => $this->createdAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function updateDatabase(array $values): void
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return;
        }

        $sets = [];
        $params = [':id' => $this->id];
        foreach ($values as $col => $val) {
            $sets[] = "`{$col}` = :{$col}";
            $params[":{$col}"] = $val;
        }

        $sql = "UPDATE `notifications` SET " . implode(', ', $sets) . " WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function getPdo(): ?PDO
    {
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
