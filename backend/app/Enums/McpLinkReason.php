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

    // Assistant link: the session token it is bound to is gone or expired (refusal and revocation)
    case SessionEnded = 'session_ended';

    // Revocations only
    case RevokedByClient = 'revoked';
    case UserDeactivated = 'user_deactivated';

    // Explicit revocation done, but the tokens could not be deleted: the client retries
    case TokensNotDeleted = 'tokens_not_deleted';
}
