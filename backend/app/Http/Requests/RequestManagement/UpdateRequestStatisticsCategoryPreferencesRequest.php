<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Services\RequestManagement\Report\ReportBranchResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/request-management/report/category-tab-preferences
 * (spec 0385): a full replacement of the actor's favourite statistics
 * categories and of the "only favourites" switch. A favourite must be a
 * report branch (ReportBranchResolver::keys(), the same allow-list the
 * dashboard's `category_keys` use), so a category the statistics never show
 * 422s. Authorization stays in the controller (`request-statistics.view`).
 */
class UpdateRequestStatisticsCategoryPreferencesRequest extends FormRequest
{
    /** Upper bound on the stored list: "select all" sends every branch of the strip, an abusive payload is far larger. */
    private const int MAX_FAVORITES = 1000;

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
            'favorite_category_ids' => ['present', 'array', 'max:'.self::MAX_FAVORITES],
            'favorite_category_ids.*' => ['integer', 'distinct', Rule::in(app(ReportBranchResolver::class)->keys())],
            'show_only_favorites' => ['required', 'boolean'],
        ];
    }
}
