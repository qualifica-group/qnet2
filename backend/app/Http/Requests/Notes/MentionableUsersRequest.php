<?php

namespace App\Http\Requests\Notes;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Notes\Concerns\ValidatesNotableEntity;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates GET /api/notes/mentionable-users (spec 0052 data_contract): the
 * host entity (ValidatesNotableEntity) plus the standard for-select query
 * (ADR 0011) — search/offset/limit/ids, reusing ForSelectQuery verbatim so
 * the response matches every other for-select endpoint's contract.
 *
 * Authorization is NOT handled here: it needs the resolved host record (D-6
 * read gate), so it runs inside NoteService::mentionableUsers.
 */
class MentionableUsersRequest extends FormRequest
{
    use ValidatesNotableEntity;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return array_merge($this->notableEntityRules(), [
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'include_total' => ['sometimes', 'boolean'],
        ]);
    }

    public function toQuery(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}
