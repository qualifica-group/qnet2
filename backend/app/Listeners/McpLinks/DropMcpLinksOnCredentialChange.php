<?php

namespace App\Listeners\McpLinks;

use App\Enums\McpLinkReason;
use App\Models\User;
use App\Services\McpLinks\McpLinkService;
use Throwable;

/**
 * Registered on the User `updated` model event, so it fires for every path that
 * changes a password (the user, an administrator, the reset flow) or
 * deactivates a user, without touching any of them.
 *
 * A failure here must never break the write that fired the event (the password
 * change would stop before the code that revokes the other sessions): it is
 * reported, and the links stay unusable anyway because the exchange refuses a
 * changed password fingerprint or an inactive user. Tokens already issued
 * survive only until their own expiry.
 */
class DropMcpLinksOnCredentialChange
{
    public function __construct(private readonly McpLinkService $service) {}

    public function handle(User $user): void
    {
        $reason = match (true) {
            $user->wasChanged('password') => McpLinkReason::PasswordChanged,
            // Deactivation, and reactivation too: a link does not survive a deactivation cycle, even if its drop failed.
            $user->wasChanged('is_active') => McpLinkReason::UserDeactivated,
            default => null,
        };

        if ($reason === null) {
            return;
        }

        try {
            $this->service->dropForUser($user, $reason);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
