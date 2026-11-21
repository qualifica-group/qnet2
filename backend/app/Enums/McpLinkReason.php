<?php

namespace App\Enums;

/**
 * Why an MCP link cannot be used: the reason of a refused exchange and, for the
 * cases that end a link, the reason stored in `revoked_reason`.
 */
enum McpLinkReason: string
{
    // Refusals only
    case LinkUnknown = 'link_unknown';
    case LinkRevoked = 'link_revoked';
    case LinkExpired = 'link_expired';
    case UserInactive = 'user_inactive';

    // Refusal (changed after opening) and revocation (changed now)
    case PasswordChanged = 'password_changed';

    // Revocations only
    case RevokedByClient = 'revoked';
    case UserDeactivated = 'user_deactivated';
}
