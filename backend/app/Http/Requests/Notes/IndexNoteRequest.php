<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Notes\NoteCursor;
use App\DataObjects\Notes\NoteQuoteScope;
use App\Http\Requests\Notes\Concerns\ValidatesNotableEntity;
use App\Http\Requests\Notes\Concerns\ValidatesQuoteOwnership;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

/**
 * Validates GET /api/notes (spec 0052 data_contract): the host entity
 * (ValidatesNotableEntity) plus an opaque keyset cursor, a `limit` counting
 * ROOT notes only (1..50, default 20 — D-13) and `quote_scope` (spec 0085,
 * D-2): `'all'` (default) | `'general'` | a numeric Offerta id belonging to
 * the host (ValidatesQuoteOwnership, AC-013) — any other value is a 422
 * (AC-014).
 *
 * Authorization is NOT handled here (it needs the resolved host record, see
 * NoteService::listForEntity via NoteEntityRegistry).
 */
class IndexNoteRequest extends FormRequest
{
    use ValidatesNotableEntity;
    use ValidatesQuoteOwnership;

    private const int DEFAULT_LIMIT = 20;

    private const int MAX_LIMIT = 50;

    /** @var array<int, string> */
    private const array FIXED_QUOTE_SCOPES = ['all', 'general'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->notableEntityRules(), [
            'cursor' => ['sometimes', 'nullable', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'quote_scope' => ['sometimes', 'nullable', 'string'],
        ]);
    }

    /**
     * A malformed (but present) cursor is a 422, not a silent "first page".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cursor = $this->query('cursor');

            if ($cursor !== null && $cursor !== '') {
                try {
                    NoteCursor::decode($cursor);
                } catch (InvalidArgumentException) {
                    $validator->errors()->add('cursor', 'The cursor is malformed.');
                }
            }

            $this->validateQuoteScopeFormat($validator);
        });
    }

    public function cursor(): ?NoteCursor
    {
        $cursor = $this->validated('cursor');

        return $cursor === null ? null : NoteCursor::decode($cursor);
    }

    public function limit(): int
    {
        $limit = $this->validated('limit');

        return $limit === null ? self::DEFAULT_LIMIT : (int) $limit;
    }

    public function quoteScope(): NoteQuoteScope
    {
        return NoteQuoteScope::fromParam($this->validated('quote_scope'));
    }

    /**
     * `'all'`/`'general'`/absent are always well-formed; a numeric value is
     * additionally checked for appartenenza (AC-013) via
     * ValidatesQuoteOwnership; anything else is malformed (AC-014).
     */
    private function validateQuoteScopeFormat(Validator $validator): void
    {
        $scope = $this->query('quote_scope');

        if ($scope === null || $scope === '' || in_array($scope, self::FIXED_QUOTE_SCOPES, true)) {
            return;
        }

        if (! ctype_digit($scope)) {
            $validator->errors()->add('quote_scope', 'The quote_scope is malformed.');

            return;
        }

        $this->validateQuoteOwnership($validator, 'quote_scope', $scope);
    }
}
