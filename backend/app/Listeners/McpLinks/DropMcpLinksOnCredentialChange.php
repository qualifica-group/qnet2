<?php

namespace App\Listeners\McpLinks;

use App\Models\McpLink;
use App\Models\User;
use App\Services\McpLinks\McpLinkService;

/**
 * Registered on the User `updated` model event, so it fires for every path that
 * changes a password (the user, an administrator, the reset flow) or
 * deactivates a user, without touching any of them.
 */
class DropMcpLinksOnCredentialChange
{
    public function __construct(private readonly McpLinkService $service) {}

    public function handle(User $user): void
    {
        $reason = match (true) {
            $user->wasChanged('password') => McpLink::REVOKED_PASSWORD_CHANGED,
            $user->wasChanged('is_active') && ! $user->is_active => McpLink::REVOKED_USER_DEACTIVATED,
            default => null,
        };

        if ($reason !== null) {
            $this->service->dropForUser($user, $reason);
        }
    }
}
