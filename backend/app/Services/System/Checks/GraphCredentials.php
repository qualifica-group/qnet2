<?php

declare(strict_types=1);

namespace App\Services\System\Checks;

/**
 * Presence (never value) of the Microsoft Graph mailer credentials, shared by
 * the email and security checks (spec 0187).
 */
final class GraphCredentials
{
    public static function present(): bool
    {
        return filled(config('mail.mailers.microsoft-graph.tenant'))
            && filled(config('mail.mailers.microsoft-graph.client'))
            && filled(config('mail.mailers.microsoft-graph.secret'));
    }
}
