<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * A FormRequest whose 403 carries a readable reason (spec 0328, AC-008): the
 * using class defines `deniedMessage()`.
 */
trait DeniesWithMessage
{
    abstract protected function deniedMessage(): string;

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException($this->deniedMessage());
    }
}
