<?php

namespace App\Services;

use App\DataObjects\Notifications\NotificationListData;
use App\DataObjects\Notifications\NotificationListResult;
use App\DataObjects\Notifications\UnreadSummary;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Business logic + DB scoping for the user notification system. Every query is
 * scoped to the given user through the `notifications` / `unreadNotifications`
 * relationships, so there is no cross-user access path (see ADR-0005).
 */
class NotificationService
{
    /**
     * Page of the user's notifications, newest first, optionally restricted to
     * unread. Counts and fetches in one scoped query each (no N+1).
     */
    public function list(User $user, NotificationListData $data): NotificationListResult
    {
        $query = $user->notifications();

        if ($data->onlyUnread) {
            $query->whereNull('read_at');
        }

        $total = $query->count();

        $items = $query
            ->orderByDesc('created_at')
            ->skip($data->offset)
            ->take($data->limit)
            ->get();

        return new NotificationListResult(
            items: $items,
            total: $total,
            offset: $data->offset,
            limit: $data->limit,
        );
    }

    /**
     * Unread count plus the most recent unread notification (cheap enough for
     * frequent polling: a count and, only when there is something to show, a
     * single indexed row). The client needs both in one call — the badge reads
     * the count, the browser tab title announces the latest one.
     */
    public function unreadSummary(User $user): UnreadSummary
    {
        $count = $user->unreadNotifications()->count();

        if ($count === 0) {
            return new UnreadSummary(count: 0);
        }

        /** @var ?DatabaseNotification $latest */
        $latest = $user->unreadNotifications()
            ->orderByDesc('created_at')
            ->first();

        return new UnreadSummary(count: $count, latest: $latest);
    }

    /**
     * Mark a single notification of the user as read and return it. Resolved
     * through the user relationship, so a foreign / unknown uuid throws
     * ModelNotFoundException → 404. Idempotent: re-marking a read notification
     * leaves it unchanged.
     */
    public function markAsRead(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark every unread notification of the user as read and return how many
     * were marked.
     */
    public function markAllAsRead(User $user): int
    {
        $marked = $user->unreadNotifications()->count();

        $user->unreadNotifications->markAsRead();

        return $marked;
    }
}
