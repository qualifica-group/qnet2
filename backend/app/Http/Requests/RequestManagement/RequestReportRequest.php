<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\RequestManagementReportRowMode;
use App\Services\RequestManagement\Report\ReportOperatorAvailabilityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/request-management/report (spec 0106 data_contract;
 * rev-2 data_contract_delta): `date_from`/`date_to`, both required `Y-m-d`,
 * `date_to >= date_from`. `category_keys` (rev-2 D-11) and `row_mode`
 * (rev-2 D-13) are BOTH required — no server-side default, the client
 * always sends an explicit choice (AC-030/AC-031).
 *
 * `format` (user directive 2026-09-08) is allow-listed against
 * `config('exports.formats')`, the same rule line CreateExportRequest uses:
 * the choice picks an ExportWriter, so an unknown value must 422 here rather
 * than reach ExportWriterFactory.
 *
 * `category_keys.*` is validated against the config allow-list
 * (backend.md §8): an unknown key 422s HERE, before it can ever reach a
 * query — the controller/generator translate the validated keys to
 * category ids via config, the raw string never touches SQL.
 *
 * Authorization is intentionally NOT handled here (stays in the controller:
 * the `request-management.report` gate), same convention as every other
 * FormRequest of this module (AssignRequestManagerGa1Request et al.).
 */
class RequestReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller (request-management.report).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'category_keys' => ['required', 'array', 'min:1'],
            'category_keys.*' => ['required', 'string', Rule::in($this->configuredCategoryKeys())],
            'row_mode' => ['required', 'string', Rule::in(array_map(
                static fn (RequestManagementReportRowMode $mode): string => $mode->value,
                RequestManagementReportRowMode::cases(),
            ))],
            'operator_keys' => ['sometimes', 'array', 'min:1'],
            // The allow-list costs a query (spec 0108 D-5), so it is resolved
            // ONLY when a selection was actually sent: an unfiltered report —
            // the default, "every operator" (D-2) — pays nothing for it.
            'operator_keys.*' => ['required', 'string', Rule::in(
                $this->has('operator_keys') ? $this->allowedOperatorKeys() : [],
            )],
            'format' => ['required', 'string', Rule::in(config('exports.formats'))],
        ];
    }

    /**
     * The GA2 selection, or NULL when the field was not sent at all — which
     * means EVERY operator (spec 0108, D-2), never "none". Kept as strings
     * because "unassigned" is one of the legal values (D-3).
     *
     * @return array<int, string>|null
     */
    public function operatorKeys(): ?array
    {
        $keys = $this->validated('operator_keys');

        return $keys === null ? null : array_values(array_map(strval(...), (array) $keys));
    }

    /**
     * @return array<int, string>
     */
    private function allowedOperatorKeys(): array
    {
        return array_column(
            app(ReportOperatorAvailabilityResolver::class)->available($this->user()),
            'key',
        );
    }

    /**
     * @return array<int, string>
     */
    private function configuredCategoryKeys(): array
    {
        return array_keys((array) config('request-management-report.branches'));
    }
}
