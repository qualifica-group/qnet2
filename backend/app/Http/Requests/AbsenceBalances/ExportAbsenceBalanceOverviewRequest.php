<?php

namespace App\Http\Requests\AbsenceBalances;

use App\Enums\ExportFormat;
use Illuminate\Validation\Rule;

/**
 * GET /api/absence-balances/overview/export (spec 0304 D-3): the overview
 * filters without paging, plus the file format.
 */
class ExportAbsenceBalanceOverviewRequest extends AbsenceBalanceOverviewRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->filterRules(),
            'format' => ['required', Rule::enum(ExportFormat::class)],
        ];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->input('format'));
    }
}
