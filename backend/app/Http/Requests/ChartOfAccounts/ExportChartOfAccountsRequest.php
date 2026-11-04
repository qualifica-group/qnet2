<?php

namespace App\Http\Requests\ChartOfAccounts;

use App\Enums\ExportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates GET /api/chart-of-accounts/export (spec 0349): the file format.
 */
class ExportChartOfAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('chart-accounts.export') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['format' => ['required', Rule::enum(ExportFormat::class)]];
    }

    public function exportFormat(): ExportFormat
    {
        return ExportFormat::from((string) $this->input('format'));
    }
}
