<?php

declare(strict_types=1);

namespace App\Exceptions\Mail;

use RuntimeException;

/**
 * Thrown instead of sending any email from a staging deployment that has no
 * MAIL_ALWAYS_TO configured: staging runs on a copy of production data, so
 * without the redirect mailbox the only safe outcome is no delivery at all.
 */
final class MailBlockedInStagingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email blocked: APP_ENV=staging requires MAIL_ALWAYS_TO to be set.');
    }
}
