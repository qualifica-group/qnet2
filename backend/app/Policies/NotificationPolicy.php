<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Authorization for the `notifications` table domain (spec 0150).
 *
 * `viewAny` is true for every authenticated user (D-1): there is no Spatie
 * permission for this resource — access is ownership, not a permission
 * string. The actual row-level boundary is enforced independently and
 * FAIL-CLOSED by NotificationsTableDefinition::baseQuery() (scoped to the
 * actor's own notifiable; a null actor sees zero rows), so `viewAny=true`
 * here only clears the table framework's mandatory gate, never widens what a
 * row query returns.
 *
 * `view`/`update` are ownership-only: a notification belongs to the actor
 * when its notifiable is the actor's own User record. Deliberately NOT
 * extending Abstracts\BasePolicy (Spatie permission-backed) — same pattern
 * as TableFilterViewPolicy. The global super-admin bypass (Gate::before in
 * AppServiceProvider) already grants every ability without needing an
 * override here, and it does not widen row visibility either, since
 * baseQuery() scopes independently of this Policy.
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->belongsTo($user, $notification);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->belongsTo($user, $notification);
    }

    private function belongsTo(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}
