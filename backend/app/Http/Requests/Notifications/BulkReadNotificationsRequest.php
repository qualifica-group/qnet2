<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/notifications/bulk-read (spec 0150).
 *
 * Authorization is intentionally NOT handled here: like every other
 * notifications endpoint this one is ownership-scoped by construction —
 * NotificationService::markManyAsRead() only ever touches the actor's OWN
 * unread notifications (a bound `whereIn` inside
 * auth()->user()->unreadNotifications()), so an id belonging to another
 * user, or unknown, is silently ignored, never a 403/404 (see ADR-0005).
 */
class BulkReadNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * The validated ids.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        /** @var array<int, string> $ids */
        $ids = $this->validated('ids', []);

        return $ids;
    }
}
