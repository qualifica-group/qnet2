<?php

namespace App\Http\Requests\Notes\Concerns;

use App\DataObjects\Notes\NoteQuoteScope;
use Illuminate\Contracts\Validation\Validator;

/**
 * `quote_scope` (spec 0085, D-2) of the requests that read a thread: `'all'`
 * (default) | `'general'` | a numeric Offerta id belonging to the host
 * (ValidatesQuoteOwnership, AC-013); any other value is a 422 (AC-014).
 * Shared by GET /api/notes and POST /api/notes/read.
 */
trait ValidatesQuoteScope
{
    use ValidatesQuoteOwnership;

    /** @var array<int, string> */
    private const array FIXED_QUOTE_SCOPES = ['all', 'general'];

    public function quoteScope(): NoteQuoteScope
    {
        return NoteQuoteScope::fromParam($this->validated('quote_scope'));
    }

    /**
     * `'all'`/`'general'`/absent are always well-formed; a numeric value is
     * additionally checked for appartenenza; anything else is malformed.
     * Runs from a Validator::after() callback on the RAW value.
     */
    protected function validateQuoteScopeFormat(Validator $validator, mixed $scope): void
    {
        if ($scope === null || $scope === '' || in_array($scope, self::FIXED_QUOTE_SCOPES, true)) {
            return;
        }

        if (! is_string($scope) || ! ctype_digit($scope)) {
            $validator->errors()->add('quote_scope', 'The quote_scope is malformed.');

            return;
        }

        $this->validateQuoteOwnership($validator, 'quote_scope', $scope);
    }
}
