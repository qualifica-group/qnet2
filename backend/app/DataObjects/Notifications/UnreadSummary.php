<?php

namespace App\DataObjects\Notifications;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Correlated result of NotificationService::unreadSummary(): how many unread
 * notifications the user has plus the most recent one, which the client uses
 * to announce the notification in the browser tab title. Returned as a DTO (not
 * a loose array) so the controller only shapes the response — see
 * standards/architecture.md -> Data Transfer Objects.
 */
final readonly class UnreadSummary
{
    public function __construct(
        public int $count,
        public ?DatabaseNotification $latest = null,
    ) {}
}
