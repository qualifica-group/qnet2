<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutPruner;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user directives 2026-11-27) on the Commessa of "ISO" and "[OLD]
 * ISO", each with every category under it:
 *   - "Standard ISO" leaves the Commessa (`iso_standards`, `standard`): the
 *     standards already live in the certificates table. Values stay in the
 *     work orders' `attribute_values`, and [OLD] PROGETTI- keeps its `standard`;
 *   - a new "Scopo" (`iso_visit_scope`, the legacy "Scopo" of "Dettagli
 *     Lavorazioni", `scopo_visita`, never migrated) on its own row right after
 *     the urgency date: on the ISO root, and in [OLD] ISO wherever the urgency
 *     date is assigned;
 *   - the ISO form takes the sections, fields and order of [OLD] ISO
 *     (IsoWorkOrderCatalogue::SECTIONS), and its labels where they differ:
 *     `iso_scope`, the continuation of "Scopo proposto", and
 *     `iso_interview_location`, "Intervista in sede".
 *
 * Assignments, labels and the [OLD] ISO forms go through the query builder (no
 * activity log, no notification); the new field and the ISO form go through
 * the model and AttributeLayoutService, like the catalogue seeders do.
 * Idempotent: a second run finds nothing to change.
 * Run with `php artisan db:seed --class=QualificaIsoWorkDetailsSeeder --force`.
 */
class QualificaIsoWorkDetailsSeeder extends Seeder
{
    use SeedsAttributeLayouts;

    public const string ISO_ROOT = IsoCatalogue::ROOT;

    public const string OLD_ISO_ROOT = QualificaOldIsoWorkOrderFieldsRemovalSeeder::ROOT;

    /** Root name => its "Standard ISO" code. */
    public const array STANDARD_CODES = [self::ISO_ROOT => 'iso_standards', self::OLD_ISO_ROOT => 'standard'];

    public const string SCOPE_CODE = 'iso_visit_scope';

    /** The [OLD] ISO urgency date the new "Scopo" follows. */
    public const string OLD_ISO_ANCHOR_CODE = 'data_urgenza_emissione';

    /** The ISO fields that take their [OLD] ISO label, from the catalogue. */
    public const array RELABELED_CODES = ['iso_scope', 'iso_interview_location'];

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly AttributeLayoutService $layouts,
    ) {}

    public function run(): void
    {
        $isoRoot = $this->root(self::ISO_ROOT);
        $oldRoot = $this->root(self::OLD_ISO_ROOT);
        $isoIds = [$isoRoot->id, ...$this->hierarchy->descendantIds($isoRoot->id)];
        $oldIds = [$oldRoot->id, ...$this->hierarchy->descendantIds($oldRoot->id)];

        DB::transaction(function () use ($isoRoot, $isoIds, $oldIds): void {
            // Step 1: "Standard ISO" out of both Commesse, values kept
            $detached = $this->detach($isoIds, self::STANDARD_CODES[self::ISO_ROOT])
                + $this->detach($oldIds, self::STANDARD_CODES[self::OLD_ISO_ROOT]);

            // Step 2: the new "Scopo" on the ISO root and beside the [OLD] ISO urgency date
            $scopeId = Attribute::query()->firstOrCreate(['code' => self::SCOPE_CODE], self::catalogueSpec(self::SCOPE_CODE))->id;
            $assigned = $this->assign($scopeId, [$isoRoot->id => 0]) + $this->assign($scopeId, $this->anchorSortOrders($oldIds));

            // Step 3: the labels of [OLD] ISO ("Scopo proposto", "Intervista in sede")
            foreach (self::RELABELED_CODES as $code) {
                DB::table('attributes')->where('code', $code)->update(['name' => self::catalogueSpec($code)['name']]);
            }

            // Step 4: the [OLD] ISO forms: "Standard ISO" out, "Scopo" after the urgency date
            $forms = $this->rewriteOldForms($oldIds);

            // Step 5: the ISO form, section by section like [OLD] ISO
            $forms += $this->rewriteIsoForm($isoRoot);

            $this->command?->info("ISO e [OLD] ISO: {$detached} assegnazioni tolte, {$assigned} aggiunte, {$forms} form aggiornati.");
        });
    }

    private function root(string $name): ProductCategory
    {
        return ProductCategory::query()->where('name', $name)->whereNull('parent_id')->firstOrFail();
    }

    /**
     * @return array{code: string, name: string, type: string}
     */
    private static function catalogueSpec(string $code): array
    {
        $spec = collect(IsoWorkOrderCatalogue::ATTRIBUTES)->firstWhere('code', $code);

        return ['code' => $spec['code'], 'name' => $spec['name'], 'type' => $spec['type']];
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function detach(array $categoryIds, string $code): int
    {
        return DB::table('attribute_category')
            ->whereIn('category_id', $categoryIds)
            ->where('context', AttributeContext::WorkOrder->value)
            ->whereIn('attribute_id', DB::table('attributes')->where('code', $code)->select('id'))
            ->delete();
    }

    /**
     * Category id => the sort order of the urgency date there.
     *
     * @param  list<int>  $categoryIds
     * @return array<int, int>
     */
    private function anchorSortOrders(array $categoryIds): array
    {
        return DB::table('attribute_category')
            ->join('attributes', 'attributes.id', '=', 'attribute_category.attribute_id')
            ->whereIn('attribute_category.category_id', $categoryIds)
            ->where('attribute_category.context', AttributeContext::WorkOrder->value)
            ->where('attributes.code', self::OLD_ISO_ANCHOR_CODE)
            ->pluck('attribute_category.sort_order', 'attribute_category.category_id')
            ->all();
    }

    /**
     * @param  array<int, int>  $sortOrders  category id => sort order
     */
    private function assign(int $attributeId, array $sortOrders): int
    {
        $now = now();
        $rows = [];

        foreach ($sortOrders as $categoryId => $sortOrder) {
            $rows[] = [
                'attribute_id' => $attributeId,
                'category_id' => $categoryId,
                'context' => AttributeContext::WorkOrder->value,
                'is_required' => false,
                'syncs_with_quote' => false,
                'sort_order' => $sortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return DB::table('attribute_category')->insertOrIgnore($rows);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function rewriteOldForms(array $categoryIds): int
    {
        $rewritten = 0;

        $layouts = DB::table('attribute_layouts')
            ->whereIn('product_category_id', $categoryIds)
            ->where('context', AttributeContext::WorkOrder->value)
            ->get(['id', 'layout']);

        foreach ($layouts as $row) {
            $layout = json_decode((string) $row->layout, true);
            $layout = is_array($layout) ? $layout : [];
            $next = self::placeAfter(self::without($layout, self::STANDARD_CODES[self::OLD_ISO_ROOT]), self::SCOPE_CODE, self::OLD_ISO_ANCHOR_CODE);

            if ($next === $layout) {
                continue;
            }

            DB::table('attribute_layouts')->where('id', $row->id)->update(['layout' => json_encode($next, JSON_UNESCAPED_UNICODE)]);
            $rewritten++;
        }

        return $rewritten;
    }

    private function rewriteIsoForm(ProductCategory $root): int
    {
        $sections = array_map(
            fn (array $definition, int $index): array => $this->layoutSection($definition[0], $definition[1], $definition[2], $index, $definition[3]),
            IsoWorkOrderCatalogue::SECTIONS,
            array_keys(IsoWorkOrderCatalogue::SECTIONS),
        );
        $current = $this->layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All);

        if ($current !== null && $this->composesAs($current, $sections)) {
            return 0;
        }

        $this->layouts->upsert($root, AttributeContext::WorkOrder, LayoutFormScope::All, ['sections' => $sections]);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private static function without(array $layout, string $code): array
    {
        // Keys beside `sections` (canonical_order) stay as they are.
        return [...$layout, ...AttributeLayoutPruner::prune($layout, static fn (?string $item): bool => $item !== $code)];
    }

    /**
     * The code on a full row of its own right after the row holding the
     * anchor; a form without the anchor is left as it is.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, mixed>
     */
    private static function placeAfter(array $layout, string $code, string $anchorCode): array
    {
        $holdsAnchor = static fn (array $row): bool => in_array($anchorCode, array_column($row['items'] ?? [], 'attribute_code'), true);
        $anchored = array_filter($layout['sections'] ?? [], static fn (array $section): bool => array_filter($section['rows'] ?? [], $holdsAnchor) !== []);

        if ($anchored === []) {
            return $layout;
        }

        $sections = [];

        foreach (self::without($layout, $code)['sections'] as $section) {
            $rows = [];

            foreach ($section['rows'] as $row) {
                $rows[] = $row;

                if ($holdsAnchor($row)) {
                    $rows[] = ['id' => "{$section['id']}-{$code}", 'items' => [['attribute_code' => $code, 'width' => 'full']]];
                }
            }

            $sections[] = [...$section, 'rows' => $rows];
        }

        return [...$layout, 'sections' => $sections];
    }
}
