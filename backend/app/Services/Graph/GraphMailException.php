<?php

declare(strict_types=1);

namespace App\Services\Graph;

use Symfony\Component\Mailer\Exception\TransportException;

/**
 * A Microsoft Graph mail send failed (spec 0175, D-1/D-6/D-12): thrown by
 * GraphMailClient and caught by SendOutboundEmailJob, whose message becomes
 * the OutboundEmail's own `error_message` -- so every message here is built
 * from a SAFE, translated, static vocabulary (lang/{it,en}/outbound_emails.php)
 * and NEVER carries the raw Graph response body or the access token.
 */
final class GraphMailException extends TransportException
{
    /**
     * `ErrorInvalidUser` (or any mailbox-not-found variant): the `from`
     * mailbox is not a real Microsoft 365 mailbox (D-6).
     */
    public static function invalidSender(string $email): self
    {
        return new self(__('outbound_emails.graph_invalid_sender', ['email' => $email]));
    }

    /**
     * 401/403: the client-credentials app registration is misconfigured
     * (wrong tenant/client/secret, or missing Mail.Send application
     * permission) -- never a per-user problem.
     */
    public static function authenticationFailed(): self
    {
        return new self(__('outbound_emails.graph_auth_error'));
    }

    /**
     * Any other non-successful Graph response. `$code` is Graph's own
     * `error.code` (e.g. `ErrorQuotaExceeded`) when present, otherwise the
     * HTTP status -- both safe to surface, unlike the response body.
     */
    public static function fromGraphError(?string $code, int $status): self
    {
        return new self(__('outbound_emails.graph_generic_error', ['code' => $code ?? (string) $status]));
    }
}
