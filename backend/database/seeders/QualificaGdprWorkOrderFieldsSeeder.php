<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\LinksLegacyAttributes;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\GdprCatalogue;
use Database\Seeders\QualificaCatalog\GdprWorkOrderCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user directive 2026-11-27): the current "GDPR" root had no
 * Commessa field. It takes the [OLD] GDPR Commessa set as fields of its own
 * (GdprWorkOrderCatalogue) with their form, inherited by "GDPR - GDPR" and
 * "GDPR - Nomina DPO", and each legacy field is linked to the new one it
 * continues, as ISO does (spec 0429), so the grids show the pair in one
 * column; the compatible [OLD] GDPR offer fields are linked to the current
 * offer fields too (GdprCatalogue::QUOTE_LINKS). The legacy fields stay on
 * the [OLD] categories with their values.
 *
 * Idempotent and non-destructive: fields keyed on `code`, assignments and
 * options additive, a Commessa form already configured on the root left
 * untouched, an existing link never overwritten.
 * Run with `php artisan db:seed --class=QualificaGdprWorkOrderFieldsSeeder --force`.
 */
class QualificaGdprWorkOrderFieldsSeeder extends Seeder
{
    use LinksLegacyAttributes;
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        // Created by QualificaCatalogSeeder: a miss must fail loudly.
        $root = ProductCategory::query()->where('name', GdprCatalogue::ROOT)->whereNull('parent_id')->firstOrFail();

        DB::transaction(function () use ($root): void {
            // Step 1: the fields, before the form validates its codes against them
            $this->seedCategoryAttributes($root, GdprWorkOrderCatalogue::ATTRIBUTES, AttributeContext::WorkOrder);

            // Step 2: the form, unless one is already configured
            $form = $this->seedForm($root);

            // Step 3: the legacy Commessa and Offerta fields point at the current ones
            $linked = $this->linkLegacyAttributes([...GdprWorkOrderCatalogue::LINKS, ...GdprCatalogue::QUOTE_LINKS]);

            $this->command?->info(sprintf('GDPR Commessa: %d campi, form %s, %d campi collegati.', count(GdprWorkOrderCatalogue::ATTRIBUTES), $form ? 'creato' : 'gia\' presente', $linked));
        });
    }

    private function seedForm(ProductCategory $root): bool
    {
        if ($this->layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All) !== null) {
            return false;
        }

        $sections = array_map(
            fn (array $definition, int $index): array => $this->layoutSection($definition[0], $definition[1], $definition[2], $index, $definition[3]),
            GdprWorkOrderCatalogue::SECTIONS,
            array_keys(GdprWorkOrderCatalogue::SECTIONS),
        );
        $this->layouts->upsert($root, AttributeContext::WorkOrder, LayoutFormScope::All, ['sections' => $sections]);

        return true;
    }
}
