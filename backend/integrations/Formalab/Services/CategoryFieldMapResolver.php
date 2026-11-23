<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Services\ProductCategories\CategoryHierarchy;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Support\FormalabField;

/**
 * Resolves the effective Formalab settings of a category and of a work order
 * (spec 0423): `enabled` comes from the branch root, the field map from the
 * nearest category of the chain declaring one. Resolved at read time, never
 * denormalised: the core does not know this table exists.
 */
final class CategoryFieldMapResolver
{
    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    /**
     * `field_map` is formalab_field => attribute_code; `field_map_owner` the
     * category of the chain that declares it (null: none does).
     *
     * @return array{enabled: bool, root: ProductCategory, field_map: array<string, string>, field_map_owner: ProductCategory|null}
     */
    public function forCategory(ProductCategory $category): array
    {
        // Step 1: the chain root-first, the category itself last, with its settings in one query
        $chain = $this->hierarchy->ancestors($category)->push($category);
        $settings = FormalabCategorySetting::query()
            ->whereIn('product_category_id', $chain->pluck('id'))
            ->get()
            ->keyBy('product_category_id');

        // Step 2: the root decides `enabled`, the nearest declaring node the map
        $root = $chain->first();
        $mapOwner = $chain->reverse()->first(static fn (ProductCategory $node): bool => $settings->get($node->id)?->field_map !== null);

        return [
            'enabled' => (bool) $settings->get($root->id)?->enabled,
            'root' => $root,
            'field_map' => $mapOwner === null ? [] : self::toAssociative($settings->get($mapOwner->id)->field_map),
            'field_map_owner' => $mapOwner,
        ];
    }

    /**
     * The field map of a work order: the union of the maps of its ENABLED
     * categories in line order, the first one winning for a Formalab field
     * and for an attribute (spec 0423 D-6).
     *
     * @return array<string, string> formalab_field => attribute_code
     */
    public function forWorkOrder(WorkOrder $workOrder): array
    {
        $workOrder->loadMissing('quoteLines.product.category');
        $merged = [];
        $categories = $workOrder->quoteLines->pluck('product.category')->filter()->unique('id');

        foreach ($categories as $category) {
            $resolved = $this->forCategory($category);

            if (! $resolved['enabled']) {
                continue;
            }

            foreach ($resolved['field_map'] as $field => $code) {
                if (! isset($merged[$field]) && ! in_array($code, $merged, true)) {
                    $merged[$field] = $code;
                }
            }
        }

        return $merged;
    }

    /**
     * Every category under an enabled root, the roots included.
     *
     * @return list<int>
     */
    public function enabledCategoryIds(): array
    {
        $rootIds = FormalabCategorySetting::query()
            ->where('enabled', true)
            ->whereHas('productCategory', static fn ($query) => $query->whereNull('parent_id'))
            ->pluck('product_category_id');

        return $rootIds
            ->flatMap(fn (int $rootId): array => [$rootId, ...$this->hierarchy->descendantIds($rootId)])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The distinct attribute codes used by the saved maps.
     *
     * @return list<string>
     */
    public function mappedCodes(): array
    {
        return FormalabCategorySetting::query()
            ->whereNotNull('field_map')
            ->pluck('field_map')
            ->flatMap(static fn (array $map): array => array_values(self::toAssociative($map)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The stored list of pairs as formalab_field => attribute_code, ignoring
     * a field this release no longer offers.
     *
     * @param  list<array{formalab_field: string, attribute_code: string}>  $pairs
     * @return array<string, string>
     */
    public static function toAssociative(array $pairs): array
    {
        $map = [];

        foreach ($pairs as $pair) {
            $field = FormalabField::tryFrom((string) ($pair['formalab_field'] ?? ''));

            if ($field !== null && ($pair['attribute_code'] ?? '') !== '') {
                $map[$field->value] = (string) $pair['attribute_code'];
            }
        }

        return $map;
    }
}
