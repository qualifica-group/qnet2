<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\AplOrientationAttributeCatalogue;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Database\Seeders\QualificaCatalog\SoaCatalogue;
use Illuminate\Database\Seeder;

/**
 * The Commessa set of the branches that have one — "SOA" (user directive
 * 2026-10-15) and "ISO" (2026-10-21): each branch's fields and form, both on
 * the root alone. The subcategories inherit the fields and, since spec 0115,
 * the form, so none needs a row of its own. Plus "Orientamento specialistico"
 * under APL (user directive 2026-11-23), whose offer fields became its
 * Commessa's, "Anagrafica Utente" synced with the offer (spec 0425).
 *
 * Kept apart from QualificaContactProcessingSeeder, which owns the Commessa
 * form of the Formazione branch only: the sets never share a category.
 *
 * Idempotent AND non-destructive: attributes keyed on `code`, assignments and
 * options additive, and a root whose Commessa form is already configured —
 * by a previous run or by hand — left untouched.
 */
class QualificaBranchWorkOrderSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    /**
     * Root name => [its Commessa fields, its Commessa form].
     *
     * @var array<string, array{0: list<array<string, mixed>>, 1: list<array{0: string, 1: string, 2: list<list<string>>, 3: array<string, mixed>}>}>
     */
    private const array BRANCHES = [
        SoaCatalogue::ROOT => [SoaCatalogue::WORK_ORDER_ATTRIBUTES, SoaCatalogue::WORK_ORDER_SECTIONS],
        IsoCatalogue::ROOT => [IsoWorkOrderCatalogue::ATTRIBUTES, IsoWorkOrderCatalogue::SECTIONS],
    ];

    /**
     * Non-root category name => [its Commessa fields, its Commessa form, the codes synced with the offer].
     *
     * @var array<string, array{0: list<array<string, mixed>>, 1: list<array{0: string, 1: string, 2: list<list<string>>, 3: array<string, mixed>}>, 2: list<string>}>
     */
    private const array CATEGORIES = [
        AplOrientationAttributeCatalogue::CATEGORY => [AplOrientationAttributeCatalogue::ATTRIBUTES, AplOrientationAttributeCatalogue::SECTIONS, AplOrientationAttributeCatalogue::SYNCED_CODES],
    ];

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        // Created by QualificaCatalogSeeder: a miss means the lists drifted
        // apart, which must fail loudly.
        foreach (self::BRANCHES as $rootName => [$attributes, $sections]) {
            $this->seedSet(ProductCategory::query()->where('name', $rootName)->whereNull('parent_id')->firstOrFail(), $attributes, $sections);
        }

        foreach (self::CATEGORIES as $name => [$attributes, $sections, $syncedCodes]) {
            $this->seedSet(ProductCategory::query()->where('name', $name)->sole(), $attributes, $sections, $syncedCodes);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $attributes
     * @param  list<array{0: string, 1: string, 2: list<list<string>>, 3: array<string, mixed>}>  $sections
     * @param  list<string>  $syncedCodes
     */
    private function seedSet(ProductCategory $category, array $attributes, array $sections, array $syncedCodes = []): void
    {
        // Step 1: the fields, before the form validates its codes against them.
        $this->seedCategoryAttributes($category, $attributes, AttributeContext::WorkOrder, $syncedCodes);

        // Step 2: the form, unless one is already configured.
        if ($this->layouts->resolveExact($category, AttributeContext::WorkOrder, LayoutFormScope::All) !== null) {
            return;
        }

        $this->layouts->upsert($category, AttributeContext::WorkOrder, LayoutFormScope::All, ['sections' => $this->sections($sections)]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: list<list<string>>, 3: array<string, mixed>}>  $definitions
     * @return list<array<string, mixed>>
     */
    private function sections(array $definitions): array
    {
        return array_map(
            fn (array $definition, int $index): array => $this->layoutSection($definition[0], $definition[1], $definition[2], $index, $definition[3]),
            $definitions,
            array_keys($definitions),
        );
    }
}
