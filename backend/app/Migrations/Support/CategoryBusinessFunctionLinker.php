<?php

namespace App\Migrations\Support;

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;

/**
 * Carries the external `business_function_id` of a product category onto the
 * qnet category (ProductCategoriesSource), remapped via the business
 * function's `old_id` — which is why `business-functions` runs in an earlier
 * phase (MigrationOrder).
 *
 * A function the import is not allowed to file a new category on is swapped
 * for a replacement of its own (REDIRECTED_FUNCTIONS).
 *
 * Every write honours the spec 0023 invariant ProductCategoryService enforces:
 * at most ONE own business function per root-to-leaf chain. The legacy stores
 * one on every node, almost always equal to its parent's, so a node below a
 * branch that already provides one inherits instead of authoring it; a
 * differing value is reported, never applied.
 */
final class CategoryBusinessFunctionLinker
{
    /**
     * Business function names the import must NOT hand to a category it
     * CREATES: imported name => the name it is filed under instead (user
     * directive 2026-09-16). The legacy catalogue files its training tree on
     * the "Formazione" function, but that function belongs to the static
     * catalogue's own "Formazione" branch, where
     * QualificaBusinessFunctionLinkSeeder assigns it: the imported nodes get a
     * function of their own instead, so the two branches never resolve to the
     * same one. Match on the name is case-insensitive.
     *
     * Deliberately out of the ADOPTION path (fillFreeSlot): an adopted
     * category is a qnet node of the static catalogue, and it keeps the
     * function the external record names.
     *
     * @var array<string, string>
     */
    private const array REDIRECTED_FUNCTIONS = [
        'Formazione' => 'FORMAZIONE OLD',
    ];

    /**
     * Source function id => the id it was redirected to, memoised for the run:
     * the redirect is resolved once instead of on every row of the branch.
     *
     * @var array<int, int>
     */
    private array $redirected = [];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * The qnet business function a NEW category authors, or null when it
     * inherits one from $parentId's branch or the reference does not resolve.
     *
     * @param  array<int, string>  $warnings
     */
    public function ownFunctionFor(mixed $externalRef, ?int $parentId, array &$warnings): ?int
    {
        $functionId = $this->resolve($externalRef, $warnings);

        if ($functionId === null) {
            return null;
        }

        // Before the branch comparison below, so that a descendant naming the
        // same redirected function still resolves to the same id and inherits
        // in silence instead of raising a spurious mismatch warning.
        $functionId = $this->redirect($functionId);

        $inherited = $this->hierarchy->inheritedBusinessFunctionFor($parentId);

        if ($inherited === null) {
            return $functionId;
        }

        if ($inherited['id'] !== $functionId) {
            $warnings[] = "Business function (external id {$externalRef}) not applied: the category inherits another one from its branch.";
        }

        return null;
    }

    /**
     * Assigns the external function to an ADOPTED category only when the slot
     * is free: no own value (a qnet assignment is never overwritten), none
     * inherited from an ancestor, none owned by a descendant.
     *
     * @param  array<int, string>  $warnings
     */
    public function fillFreeSlot(ProductCategory $category, mixed $externalRef, array &$warnings): void
    {
        if ($category->business_function_id !== null || $this->hierarchy->inheritedBusinessFunctionFor($category->parent_id) !== null) {
            return;
        }

        $descendantIds = $this->hierarchy->descendantIds($category->id);

        if ($descendantIds !== [] && ProductCategory::query()->whereIn('id', $descendantIds)->whereNotNull('business_function_id')->exists()) {
            return;
        }

        $category->business_function_id = $this->resolve($externalRef, $warnings);
    }

    /**
     * After a detached category is relinked: once its new branch provides a
     * function, its own value and every descendant's give way to it — the
     * same cascade ProductCategoryService applies on a reparent.
     */
    public function realignAfterRelink(ProductCategory $category): void
    {
        if ($this->hierarchy->inheritedBusinessFunctionFor($category->parent_id) === null) {
            return;
        }

        $ids = [$category->id, ...$this->hierarchy->descendantIds($category->id)];

        ProductCategory::query()->whereIn('id', $ids)->whereNotNull('business_function_id')->update(['business_function_id' => null]);
    }

    /**
     * Swaps a REDIRECTED_FUNCTIONS match for its replacement, created on first
     * use. The replacement is a qnet row (no `old_id`), so no later import
     * claims it, and `name` is its natural key exactly as it is for the links
     * QualificaBusinessFunctionLinkSeeder makes.
     */
    private function redirect(int $functionId): int
    {
        if (array_key_exists($functionId, $this->redirected)) {
            return $this->redirected[$functionId];
        }

        $name = trim((string) BusinessFunction::query()->whereKey($functionId)->value('name'));

        foreach (self::REDIRECTED_FUNCTIONS as $importedName => $replacementName) {
            if (strcasecmp($name, $importedName) !== 0) {
                continue;
            }

            return $this->redirected[$functionId] = BusinessFunction::query()
                ->firstOrCreate(['name' => $replacementName])
                ->id;
        }

        return $this->redirected[$functionId] = $functionId;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolve(mixed $externalRef, array &$warnings): ?int
    {
        if ($externalRef === null || $externalRef === '') {
            return null;
        }

        /** @var int|null $id */
        $id = BusinessFunction::query()->where('old_id', $externalRef)->value('id');

        if ($id === null) {
            $warnings[] = "Unresolved business_function_id (external id {$externalRef}); category left without a business function.";
        }

        return $id;
    }
}
