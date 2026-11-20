<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\ReportStatusColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/request-management/report/column-statuses (spec 0391):
 * the full replacement of the statuses every status-based column counts.
 * Every column key is required (an empty list switches the column off), no
 * other key is accepted, and each id must be an existing working status.
 * Authorization stays in the controller (`request-statistics.update`).
 */
class UpdateRequestStatisticsColumnStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = ['columns' => ['required', 'array:'.implode(',', ReportStatusColumn::values())]];

        foreach (ReportStatusColumn::values() as $key) {
            $rules["columns.{$key}"] = ['present', 'array'];
            $rules["columns.{$key}.*"] = ['integer', 'distinct', Rule::exists('quote_workflow_statuses', 'id')];
        }

        return $rules;
    }
}
