<?php

declare(strict_types=1);

namespace App\Http\Requests\AgentSalesReport;

use App\Http\Requests\Export\CreateExportRequest;
use Illuminate\Support\Arr;

/**
 * Grid state of the two sync exports of the Report Vendite Agenti (spec 0365, D-9): the generic
 * export's own state validation (colId / filter allow-lists of the `agent-sales-report`
 * domain, resolved from the route default), without `format` (always xlsx) and with `columns`
 * accepted but ignored.
 */
class ExportAgentSalesReportRequest extends CreateExportRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('agent-sales-report.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['format', 'columns', 'columns.*.colId', 'columns.*.header']),
            'columns' => ['sometimes', 'array'],
        ];
    }

    /**
     * The grid state the offers are selected with.
     *
     * @return array<string, mixed>
     */
    public function gridState(): array
    {
        return $this->safe()->only(['sortModel', 'filterModel', 'advancedFilters', 'customFilterRules', 'search']);
    }
}
