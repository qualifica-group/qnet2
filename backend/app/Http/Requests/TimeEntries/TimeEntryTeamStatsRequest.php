<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Http\Requests\TimeEntries\Concerns\ValidatesTimeEntryFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET stats/team (spec 0122, data_contract): only the Periodo slice of F —
 * `period_preset`/`date_from`/`date_to` — the vista team has no per-user or
 * entry-level filters (D-10). Reuses `ValidatesTimeEntryFilters` verbatim
 * (same rules, same max-range `after()` check) narrowed to those three
 * fields via `array_intersect_key`, rather than duplicating the date/preset
 * validation rules a second time.
 */
class TimeEntryTeamStatsRequest extends FormRequest
{
    use ValidatesTimeEntryFilters;

    /**
     * @var list<string>
     */
    private const array PERIOD_FIELDS = ['period_preset', 'date_from', 'date_to'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_intersect_key($this->timeEntryFilterRules(), array_flip(self::PERIOD_FIELDS));
    }

    public function withValidator(Validator $validator): void
    {
        $this->withTimeEntryFilterValidation($validator);
    }

    public function filter(): TimeEntryFilterData
    {
        return $this->timeEntryFilterData();
    }
}
