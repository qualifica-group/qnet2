<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\RequestManagementReportRowMode;
use App\Services\RequestManagement\Report\ReportOperatorAvailabilityResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates GET /api/request-management/report/dashboard (spec 0107
 * data_contract): the same four filters as POST /report (spec 0106 rev-2),
 * read from the query string instead of the body — `category_keys.*` is
 * validated against the config allow-list here too, so an unknown key 422s
 * before it can ever reach a query (backend.md §8).
 *
 * Authorization is intentionally NOT handled here (stays in the controller:
 * `request-management.report`, reused verbatim per spec 0107 D-6), same
 * convention as RequestReportRequest.
 */
class RequestDashboardRequest extends FormRequest
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
