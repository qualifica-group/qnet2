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
 * actor works on (ActorParentCategories, user directive 2026-11-12) and every
 * value is clamped to them on the server, so the picker is never the only guard.
 *
 * `sendDefault` makes the page send its preset explicitly, so a request that
 * omits it (a grid inside a record, which hides these filters, or an API
 * client) is not narrowed; `required` keeps the field from being left empty.
 */
final class PresetListFilters
{
    public const string PARENT_CATEGORY = 'parent_category';

    public const string PRODUCT_CATEGORY = 'product_category';

    /** Source param of the root picker: list only the roots of the actor (ProductCategoryRootForSelectRequest). */
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
    public static function parentCategoryFilter(string $labelNamespace, int $order, ?CategoryUsageArea $area = null): array
    {
        return self::categoryFilter(self::PARENT_CATEGORY, "{$labelNamespace}.advancedFilters.parentCategory", $order, ['resource' => 'product-category-roots', 'params' => ['hide_linked_legacy' => 1, self::ACTOR_ROOTS_PARAM => 1, ...self::areaParam($area)]]);
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
     * Fills a parent category filter with the actor's competence.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    public function presetParentCategory(array $filter, ?User $actor): array
    {
        return [...$filter, 'required' => true, 'sendDefault' => true, 'defaultValue' => $this->defaultParentCategories($actor)];
    }

    /**
     * The categories selected by a parent category value, every picked root
     * with its descendants and, as a group (spec 0374 D-12), the legacy roots
     * linked to it; null when nothing narrows ("Tutte" picked by an actor with
     * no limit). For a limited actor the picks are clamped to their roots:
     * "Tutte", or a pick outside them, stands for all of them.
     *
     * @return array<int, int>|null
     */
    public function branchIds(mixed $value, ?User $actor): ?array
    {
        $rootIds = self::ids($value);
        $allowedRootIds = $this->actorRoots->rootIdsFor($actor);

        if ($allowedRootIds !== null) {
            $rootIds = array_values(array_intersect($this->groups->currentRootIds($rootIds), $allowedRootIds));
            $rootIds = $rootIds === [] ? $allowedRootIds : $rootIds;
        } elseif (in_array(ProductCategoryForSelectResolver::ALL_ROOTS_ID, $rootIds, true)) {
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
     * "Tutte" for an actor with no limit, every root of theirs otherwise.
     *
     * @return array<int, int>
     */
    private function defaultParentCategories(?User $actor): array
    {
        return $this->actorRoots->rootIdsFor($actor) ?? [ProductCategoryForSelectResolver::ALL_ROOTS_ID];
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
