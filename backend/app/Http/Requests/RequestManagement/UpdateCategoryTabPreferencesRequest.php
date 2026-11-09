<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Http\Requests\CategoryTabs\CategoryTabLevelRequest;
use Illuminate\Validation\Rule;

/**
 * Validates PUT /api/{module}/category-tab-preferences (spec 0184): a full
 * replacement of the actor's favourite categories and of the "only
 * favourites" switch. `favorite_category_ids` is `present`, so an empty list
 * clears the favourites while a missing key is rejected.
 *
 * The optional `level` query (spec 0337) selects the root-level favourites row.
 * Authorization stays in the controller (`{module}.viewAny`), same convention
 * as the module's other requests.
 */
class UpdateCategoryTabPreferencesRequest extends CategoryTabLevelRequest
{
    /** Upper bound on the stored list: "select all" sends every category of the strip, an abusive payload is far larger. */
    private const int MAX_FAVORITES = 1000;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'favorite_category_ids' => ['present', 'array', 'max:'.self::MAX_FAVORITES],
            'favorite_category_ids.*' => ['integer', 'distinct', Rule::exists('product_categories', 'id')],
            'show_only_favorites' => ['required', 'boolean'],
        ];
    }
}
