<?php

declare(strict_types=1);

namespace App\Tables\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Models\Notification;

/**
 * Row projection for the `notifications` domain (spec 0150). Normalizes the
 * stored payload through the SAME value object NotificationResource uses
 * (App\DataObjects\Notifications\NotificationData), so the grid and the
 * campanella agree on `title`/`message`/`level`/`action_url` byte for byte —
 * including the invalid-level → `info` fallback (AC-003).
 *
 * `status` is derived from `read_at`'s nullity, never persisted.
 */
final class NotificationRowMapper
{
    /**
     * @return array<string, mixed>
     */
    public function map(Notification $row): array
    {
        $data = NotificationData::fromArray($row->data ?? []);

        return [
            'id' => $row->id,
            'status' => $row->read_at === null ? 'unread' : 'read',
            'title' => $data->title,
            'message' => $data->message,
            'level' => $data->level->value,
            'created_at' => $row->created_at,
            'read_at' => $row->read_at,
            'action_url' => $data->actionUrl,
        ];
    }
}
