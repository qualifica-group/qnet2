<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\AdvancedFilterType;
use App\Enums\CategoryUsageArea;
use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;

/**
 * The category filters of the record list pages (Commesse, Opportunità,
 * Offerte, Contratti). The "Categoria madre" picker lists only the roots the
 * actor works on in that module (ActorParentCategories, user directives
 * 2026-11-12 and 2026-11-16) and every value is clamped to them on the server,
 * so the picker is never the only guard.
 *
 * `sendDefault` makes the page send its preset explicitly, so a request that
 * omits it (a grid inside a record, which hides these filters, or an API
 * client) is not narrowed; `required` keeps the field from being left empty.
 */
final class PresetListFilters
{
    public const string PARENT_CATEGORY = 'parent_category';

    public const string PRODUCT_CATEGORY = 'product_category';

    /**
     * Source param of the root picker: list only the roots of the actor in the
     * module it names (ParentCategoryListModule, ProductCategoryRootForSelectRequest).
     */
    public const string ACTOR_ROOTS_PARAM = 'actor_roots';

    /** Same whereIn cardinality cap as the generic AdvancedFilterApplier. */
    private const int MAX_IDS = 500;

    public function __construct(
        private readonly ProductCategoryBranches $branches,
        private readonly LinkedCategoryGroups $groups,
        private readonly ActorParentCategories $actorRoots,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function parentCategoryFilter(string $labelNamespace, int $order, ParentCategoryListModule $module, ?CategoryUsageArea $area = null): array
    {
        return self::categoryFilter(self::PARENT_CATEGORY, "{$labelNamespace}.advancedFilters.parentCategory", $order, ['resource' => 'product-category-roots', 'params' => ['hide_linked_legacy' => 1, self::ACTOR_ROOTS_PARAM => $module->value, ...self::areaParam($area)]]);
    }

    /**
     * The module named by a parent category descriptor among $descriptors; null when there is none.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     */
    public static function moduleOf(array $descriptors): ?ParentCategoryListModule
    {
        foreach ($descriptors as $descriptor) {
            $module = $descriptor['source']['params'][self::ACTOR_ROOTS_PARAM] ?? null;

            if (($descriptor['name'] ?? null) === self::PARENT_CATEGORY && is_string($module)) {
                return ParentCategoryListModule::tryFrom($module);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function productCategoryFilter(string $labelNamespace, int $order, ?CategoryUsageArea $area = null): array
    {
        return self::categoryFilter(self::PRODUCT_CATEGORY, "{$labelNamespace}.advancedFilters.productCategory", $order, ['resource' => 'product-categories', 'params' => ['include_inactive' => 1, ...self::areaParam($area)]]);
    }

    /**
     * Spec 0389 rev.2: the module a grid's category filter lists categories for; none = every category.
     *
     * @return array<string, string>
     */
    public static function areaParam(?CategoryUsageArea $area): array
    {
        return $area === null ? [] : ['usage_area' => $area->value];
    }

    /**
     * The parent category filter of a list page (user directive 2026-11-12):
     * empty by default, and the page sends it even empty, because empty means
     * "every root of the actor" (branchIds()), not "no filter".
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    public static function presetParentCategory(array $filter): array
    {
        return [...$filter, 'sendDefault' => true, 'defaultValue' => []];
    }

    /**
     * The roots a parent category value narrows to: for a limited actor the
     * picks clamped to their roots, where no pick, "Tutte" (the sentinel of
     * older saved views) or a pick outside them stands for all of them; for an
     * actor with no limit the picks, null when there is none or "Tutte".
     *
     * @return array<int, int>|null
     */
    public function effectiveRootIds(mixed $value, ?User $actor, ParentCategoryListModule $module): ?array
    {
        $rootIds = self::ids($value);
        $allowedRootIds = $this->actorRoots->rootIdsFor($actor, $module);

        if ($allowedRootIds !== null) {
            $picked = $this->pickedAllowedRootIds($rootIds, $allowedRootIds);

            return $picked === [] ? $allowedRootIds : $picked;
        }

        return $rootIds === [] || in_array(ProductCategoryForSelectResolver::ALL_ROOTS_ID, $rootIds, true) ? null : $rootIds;
    }

    /**
     * Whether a limited actor's value falls back to every root of theirs (no
     * pick, "Tutte" or picks all outside them) rather than narrowing to roots
     * they picked. False for an actor with no limit: nothing narrows them.
     */
    public function isActorDefault(mixed $value, ?User $actor, ParentCategoryListModule $module): bool
    {
        $allowedRootIds = $this->actorRoots->rootIdsFor($actor, $module);

        return $allowedRootIds !== null && $this->pickedAllowedRootIds(self::ids($value), $allowedRootIds) === [];
    }

    /**
     * The categories of effectiveRootIds(), every root with its descendants
     * and, as a group (spec 0374 D-12), the legacy roots linked to it; null
     * when nothing narrows.
     *
     * @return array<int, int>|null
     */
    public function branchIds(mixed $value, ?User $actor, ParentCategoryListModule $module): ?array
    {
        $rootIds = $this->effectiveRootIds($value, $actor, $module);

        if ($rootIds === null) {
            return null;
        }

        $branchIds = [];

        foreach ($this->groups->withLinkedLegacy($this->groups->currentRootIds($rootIds)) as $rootId) {
            $branchIds = [...$branchIds, ...$this->branches->branchIds($rootId)];
        }

        return array_values(array_unique($branchIds));
    }

    /**
     * @return array<int, int>
     */
    public static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_slice(array_map(intval(...), array_values(array_filter($value, is_numeric(...)))), 0, self::MAX_IDS);
    }

    /**
     * @param  array<int, int>  $rootIds
     * @param  array<int, int>  $allowedRootIds
     * @return array<int, int>
     */
    private function pickedAllowedRootIds(array $rootIds, array $allowedRootIds): array
    {
        return array_values(array_intersect($this->groups->currentRootIds($rootIds), $allowedRootIds));
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private static function categoryFilter(string $name, string $label, int $order, array $source): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'type' => AdvancedFilterType::Relation,
            'order' => $order,
            'required' => false,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'source' => $source,
            'target' => $name,
        ];
    }
}
