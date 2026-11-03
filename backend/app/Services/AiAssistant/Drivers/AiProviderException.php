<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Drivers;

use RuntimeException;

/**
 * Provider failure with a message safe to show to the user: it never carries
 * the provider URL, API key or response body (those go to the log, sanitized).
 */
final class AiProviderException extends RuntimeException
{
    public const GENERIC_MESSAGE = 'The AI assistant could not complete the request. Please try again.';

    public function __construct()
    {
        parent::__construct(self::GENERIC_MESSAGE);
    }
}
