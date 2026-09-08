<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\RequestManagementReportRowMode;
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
        ];
    }

    /**
     * @return array<int, string>
     */
    private function configuredCategoryKeys(): array
    {
        return array_keys((array) config('request-management-report.branches'));
    }
}
