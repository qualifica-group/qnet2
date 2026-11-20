<?php

declare(strict_types=1);

namespace Integrations\Formalab\Exceptions;

use RuntimeException;

/**
 * A Formalab call that did not produce a usable answer: not configured,
 * unreachable, or answered with an error status (carried in $status).
 */
class FormalabException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
