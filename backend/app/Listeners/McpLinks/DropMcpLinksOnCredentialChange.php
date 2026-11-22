<?php

namespace App\Listeners\McpLinks;

use App\Enums\McpLinkReason;
use App\Models\User;
use App\Services\McpLinks\McpLinkService;
use Throwable;

/**
 * Registered on the User model events, so it covers every path that changes a
 * password (the user, an administrator, the reset flow, the first access, the
 * resent credentials) or the `is_active` flag, without touching any of them.
 *
 * Two policies, on purpose:
 *
 * - Password change (`updated`): the drop is best effort and an ordinary failure is
 *   reported without interrupting the password change or what the existing code does
 *   next (revoking the other sessions). The defence does not depend on it: the
 *   exchange and EnforceMcpLinkLife refuse a link whose password fingerprint no
 *   longer matches.
 * - `is_active` change (`updating`, that is BEFORE the write): the password does not
 *   change, so marking the links fallen is the only durable defence. A failure
 *   propagates and the write never happens: there is no state "user deactivated or
 *   reactivated with live links", even for callers that use no transaction.
 *   Marking before the write may leave links fallen for an update that then fails,
 *   which is the safe side.
 *
 * An error that invalidates the caller's transaction (a deadlock, a lost
 * connection) always propagates: swallowing it would let the caller go on in
 * autocommit on a transaction that is gone.
 */
class DropMcpLinksOnCredentialChange
{
    public function __construct(private readonly McpLinkService $service) {}

    public function beforeUpdate(User $user): void
    {
        if ($user->isDirty('is_active')) {
            // Deactivation, and reactivation too: a link does not survive a deactivation cycle.
            $this->service->dropForUser($user, McpLinkReason::UserDeactivated);
        }
    }

    public function afterUpdate(User $user): void
    {
        if (! $user->wasChanged('password')) {
            return;
        }

        try {
            $this->service->dropForUser($user, McpLinkReason::PasswordChanged);
        } catch (Throwable $e) {
            if (McpLinkService::invalidatesTransaction($e)) {
                throw $e;
            }

            report($e);
        }
    }
}
