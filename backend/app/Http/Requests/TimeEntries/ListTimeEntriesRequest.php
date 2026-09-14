<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryListQuery;
use App\Http\Requests\TimeEntries\Concerns\ValidatesTimeEntryFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/time-entries (spec 0122, data_contract): F plus sort/pagination.
 * `sort_by` is an ALLOW-LIST (`date|target_minutes|is_active`, backend.md
 * §8 — never `orderByRaw`); `per_page` is capped at
 * `config('time_entries.max_per_page')`.
 *
 * Authorization is NOT handled here: rule R (`TimeEntryReadAuthorizer`) runs
 * inside `TimeEntryDaySetBuilder`, since it depends on the RESOLVED
 * `user_id`, not the raw payload.
 */
class ListTimeEntriesRequest extends FormRequest
{
    use ValidatesTimeEntryFilters;

    /**
     * @var list<string>
     */
    private const array SORTABLE_FIELDS = ['date', 'target_minutes', 'is_active'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->timeEntryFilterRules(),
            'sort_by' => ['sometimes', 'string', Rule::in(self::SORTABLE_FIELDS)],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.config('time_entries.max_per_page')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->withTimeEntryFilterValidation($validator);
    }

    public function toQuery(): TimeEntryListQuery
    {
        return new TimeEntryListQuery(
            filter: $this->timeEntryFilterData(),
            sortBy: (string) ($this->validated('sort_by') ?? 'date'),
            sortDirection: (string) ($this->validated('sort_direction') ?? 'asc'),
            page: (int) ($this->validated('page') ?? 1),
            perPage: (int) ($this->validated('per_page') ?? config('time_entries.default_per_page')),
        );
    }
}
