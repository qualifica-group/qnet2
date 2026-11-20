<?php

namespace App\Http\Requests\ProductCategories;

use App\Enums\CategoryUsageArea;
use App\Tables\Shared\ParentCategoryListModule;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Validation\Rule;

/**
 * The root picker query: the branch picker's plus `current_only` (spec 0374),
 * which keeps the non-legacy roots and drops the "Tutte" option, and
 * `hide_linked_legacy` (spec 0374 D-12), which hides the legacy roots linked to
 * a current one (`ids[]` still resolves them), and `actor_roots` (user directives
 * 2026-11-12 and 2026-11-16), a module key keeping only the roots the actor
 * works on in that module.
 */
class ProductCategoryRootForSelectRequest extends ProductCategoryBranchForSelectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'current_only' => ['sometimes', 'boolean'],
            'hide_linked_legacy' => ['sometimes', 'boolean'],
            PresetListFilters::ACTOR_ROOTS_PARAM => ['sometimes', Rule::enum(ParentCategoryListModule::class)],
            // Spec 0389 rev.2: only the roots used in that module.
            'usage_area' => ['sometimes', Rule::enum(CategoryUsageArea::class)->except(CategoryUsageArea::All)],
        ];
    }

    public function hideLinkedLegacy(): bool
    {
        return (bool) $this->validated('hide_linked_legacy', false);
    }

    /** The module whose actor roots the picker lists; null = every root. */
    public function actorRootsModule(): ?ParentCategoryListModule
    {
        $module = $this->validated(PresetListFilters::ACTOR_ROOTS_PARAM);

        return is_string($module) ? ParentCategoryListModule::from($module) : null;
    }

    public function currentOnly(): bool
    {
        return (bool) $this->validated('current_only', false);
    }
}
