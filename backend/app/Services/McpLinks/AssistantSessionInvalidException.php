<?php

namespace App\Services\McpLinks;

use RuntimeException;

/**
 * The token given to open an assistant link is not a live session token of that user.
 */
class AssistantSessionInvalidException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The token is not a live session token of the user.');
    }
}
