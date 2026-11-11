<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Enums\AdvancedFilterType;
use App\Enums\CategoryUsageArea;
use App\Models\User;
use App\Products\ProductCategoryBranches;
use App\Services\Assignment\ActorCompetenceReach;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;

/**
 * The advanced filters the record list pages open with already filled in,
 * shared by Commesse, Opportunità, Offerte and Contratti.
 *
 * `sendDefault` makes the page send its preset explicitly, so a request that
 * omits it (a grid inside a record, which hides these filters, or an API
 * client) is not narrowed; `required` keeps the field from being left empty.
 */
final class PresetListFilters
{
    public const string ASSIGNMENT = 'assignment';

    public const string PARENT_CATEGORY = 'parent_category';

    public const string PRODUCT_CATEGORY = 'product_category';

    /** Same whereIn cardinality cap as the generic AdvancedFilterApplier. */
    private const int MAX_IDS = 500;

    public function __construct(
        private readonly ProductCategoryBranches $branches,
        private readonly LinkedCategoryGroups $groups,
    ) {}

    /**
     * @param  array<int, string>  $defaultValue
     * @param  array<int, string>  $excludedValues  options the actor gains nothing from
     * @return array<string, mixed>
     */
    public static function assignmentFilter(string $labelNamespace, string $enumKey, array $defaultValue, array $excludedValues): array
    {
        return [
            'name' => self::ASSIGNMENT,
            'label' => "{$labelNamespace}.advancedFilters.assignment",
            'type' => AdvancedFilterType::Enum,
            'order' => 0,
            'required' => true,
            'sendDefault' => true,
            'visible' => true,
            'width' => 'md',
            'multiple' => true,
            'target' => self::ASSIGNMENT,
            'enumKey' => $enumKey,
            'defaultValue' => $defaultValue,
            'excludedValues' => $excludedValues,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function parentCategoryFilter(string $labelNamespace, int $order, ?CategoryUsageArea $area = null): array
    {
        return self::categoryFilter(self::PARENT_CATEGORY, "{$labelNamespace}.advancedFilters.parentCategory", $order, ['resource' => 'product-category-roots', 'params' => ['hide_linked_legacy' => 1, ...self::areaParam($area)]]);
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
     * linked to it; null when "Tutte" is among the picks.
     *
     * @return array<int, int>|null
     */
    public function branchIds(mixed $value): ?array
    {
        $rootIds = self::ids($value);

        if (in_array(ProductCategoryForSelectResolver::ALL_ROOTS_ID, $rootIds, true)) {
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
     * The roots of the branches the actor's competence touches, a linked legacy root as its current one. An actor with
     * no competence, or with every category, has nothing to preset and starts
     * on "Tutte" (as spec 0193 D-2 does for the category tabs).
     *
     * @return array<int, int>
     */
    private function defaultParentCategories(?User $actor): array
    {
        $reach = $actor === null ? null : ActorCompetenceReach::for($actor);

        if ($reach === null || $reach->all || $reach->isEmpty()) {
            return [ProductCategoryForSelectResolver::ALL_ROOTS_ID];
        }

        $rootIds = $this->groups->currentRootIds(array_values(array_unique(array_map($this->branches->rootIdOf(...), $reach->categoryIds))));
        sort($rootIds);

        return $rootIds;
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
