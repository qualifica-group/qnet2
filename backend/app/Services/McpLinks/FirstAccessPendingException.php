<?php

namespace App\Services\McpLinks;

use RuntimeException;

class FirstAccessPendingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The user must choose the first password.');
    }
}
