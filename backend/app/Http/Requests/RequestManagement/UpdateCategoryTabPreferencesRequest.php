<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/{module}/category-tab-preferences (spec 0184): a full
 * replacement of the actor's favourite categories and of the "only
 * favourites" switch. `favorite_category_ids` is `present`, so an empty list
 * clears the favourites while a missing key is rejected.
 *
 * Authorization stays in the controller (`{module}.viewAny`), same convention
 * as the module's other requests.
 */
class UpdateCategoryTabPreferencesRequest extends FormRequest
{
    /** Upper bound on the stored list: far above any real strip, well below an abusive payload. */
    private const int MAX_FAVORITES = 200;

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
            'favorite_category_ids.*' => ['integer', 'distinct', Rule::exists('product_categories', 'id')],
            'show_only_favorites' => ['required', 'boolean'],
        ];
    }
}
