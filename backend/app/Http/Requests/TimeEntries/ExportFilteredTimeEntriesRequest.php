<?php

declare(strict_types=1);

namespace App\Http\Requests\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Http\Requests\TimeEntries\Concerns\ValidatesTimeEntryFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/time-entries/exports/filtered (spec 0122, data_contract,
 * AC-024): F, exactly — same shape/rules as `TimeEntryStatsRequest`, kept as
 * its own class since the `time-entries.export` ability check lives in the
 * controller (mirrors `ListTimeEntriesRequest`'s own split between
 * validation and authorization; rule R itself runs inside
 * `TimeEntryExportService`, via `TimeEntryDaySetBuilder`).
 */
class ExportFilteredTimeEntriesRequest extends FormRequest
{
    use ValidatesTimeEntryFilters;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->timeEntryFilterRules();
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
