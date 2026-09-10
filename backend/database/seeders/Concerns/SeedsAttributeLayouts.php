<?php

namespace Database\Seeders\Concerns;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Illuminate\Support\Collection;

/**
 * Builds a layout blob's sections (spec 0062, layout-contract) for the seeders
 * that group the client's reference attributes into form sections — the whole
 * Offerta form (QualificaQuoteLayoutSeeder: "Dati corso", "Dati Aula" and
 * "Dati Lavorazione Contatto") and the Commessa one
 * (QualificaContactProcessingSeeder).
 *
 * Only the BLOB is built here: each seeder owns which categories it writes to
 * and injects AttributeLayoutService itself, because that Service validates
 * every `attribute_code` against the target category's own effective
 * attributes (an allow-list this trait cannot see).
 */
trait SeedsAttributeLayouts
{
    /**
     * Two fields per row (ui-design.md §2: compact by default), collapsing to
     * one column on a narrow panel through the renderer's container queries.
     */
    protected const int LAYOUT_SECTION_COLUMNS = 2;

    /**
     * @param  list<list<string>>  $rows  attribute codes, one inner list per rendered row
     * @return array<string, mixed>
     */
    protected function layoutSection(string $id, string $title, array $rows, int $sortOrder): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => null,
            'variant' => LayoutSectionVariant::Default->value,
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => self::LAYOUT_SECTION_COLUMNS,
            'sort_order' => $sortOrder,
            'rows' => array_map(
                static fn (array $codes, int $index): array => [
                    // Deterministic ids: a re-seed of a reset category rebuilds
                    // the identical blob instead of churning random uuids.
                    'id' => sprintf('%s-%d', $id, $index),
                    'items' => array_map(
                        static fn (string $code): array => [
                            'attribute_code' => $code,
                            'width' => LayoutItemWidth::Half->value,
                        ],
                        $codes,
                    ),
                ],
                $rows,
                array_keys($rows),
            ),
        ];
    }

    /**
     * Defensive cap on the depth walk, mirroring CategoryHierarchy's: only
     * corrupted data could loop, the write-side anti-cycle guard prevents a
     * real cycle from being persisted.
     */
    private const int MAX_DEPTH = 100;

    /**
     * Whether $category needs no row of its own because the ancestor it
     * inherits from already renders EXACTLY $sections (spec 0115).
     *
     * Only the ancestors are consulted, never $category's own row, so this
     * answers the same way before and after that row is dropped. It follows
     * that the caller must walk its categories ROOT-FIRST (see
     * sortRootFirst): what an ancestor renders is decided by the pass that
     * already visited it.
     *
     * @param  list<array<string, mixed>>  $sections
     */
    protected function inheritedRendersSame(
        AttributeLayoutService $layouts,
        ProductCategory $category,
        AttributeContext $context,
        array $sections,
    ): bool {
        [$inherited] = $layouts->resolveInheritedForScope($category, $context, LayoutFormScope::All);

        return $inherited !== null && $inherited == ['sections' => $sections];
    }

    /**
     * $categories ordered by depth, shallowest first — the order
     * inheritedRendersSame() depends on.
     *
     * @param  Collection<int, ProductCategory>  $categories
     * @param  array<int, int|null>  $parentIdMap  CategoryHierarchy::parentIdMap()
     * @return Collection<int, ProductCategory>
     */
    protected function sortRootFirst(Collection $categories, array $parentIdMap): Collection
    {
        return $categories
            ->sortBy(static function (ProductCategory $category) use ($parentIdMap): int {
                $depth = 0;
                $parentId = $category->parent_id;

                while ($parentId !== null && $depth < self::MAX_DEPTH) {
                    $parentId = $parentIdMap[$parentId] ?? null;
                    $depth++;
                }

                return $depth;
            })
            ->values();
    }

    /**
     * Keeps only the codes the target category actually resolves, then drops
     * the rows left empty — a category opting out of an ancestor's assignments
     * must not carry an item pointing at an attribute it does not have.
     *
     * @param  list<list<string>>  $rows
     * @param  list<string>  $allowedCodes
     * @return list<list<string>>
     */
    protected function keepAllowedCodes(array $rows, array $allowedCodes): array
    {
        $kept = array_map(
            static fn (array $row): array => array_values(array_intersect($row, $allowedCodes)),
            $rows,
        );

        return array_values(array_filter($kept, static fn (array $row): bool => $row !== []));
    }
}
