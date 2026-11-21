<?php

namespace App\Services\McpLinks;

use RuntimeException;

class TooManyOpenAttemptsException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many attempts.');
    }
}
