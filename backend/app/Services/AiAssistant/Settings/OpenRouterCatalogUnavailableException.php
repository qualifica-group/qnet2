<?php

declare(strict_types=1);

namespace App\Services\AiAssistant\Settings;

use RuntimeException;

/**
 * OpenRouter did not answer usefully (network failure, non-2xx, malformed body).
 * The message is a fixed string: never the URL, the key or the remote body.
 */
final class OpenRouterCatalogUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('OpenRouter catalog unavailable.');
    }
}
