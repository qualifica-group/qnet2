<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\SoaCatalogue;
use Illuminate\Database\Seeder;

/**
 * The Commessa set of the "SOA" branch (user directive 2026-10-15): the
 * fields of SoaCatalogue::WORK_ORDER_ATTRIBUTES and their form, both on the
 * root alone. The subcategories inherit the fields and, since spec 0115, the
 * form, so neither needs a row of its own.
 *
 * Kept apart from QualificaContactProcessingSeeder, which owns the Commessa
 * form of the Formazione branch only: the two sets never share a category.
 *
 * Idempotent AND non-destructive: attributes keyed on `code`, assignments and
 * options additive, and a root whose Commessa form is already configured —
 * by a previous run or by hand — left untouched.
 */
class QualificaSoaWorkOrderSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        // Created by QualificaCatalogSeeder: a miss means the two lists
        // drifted apart, which must fail loudly.
        $root = ProductCategory::query()->where('name', SoaCatalogue::ROOT)->whereNull('parent_id')->firstOrFail();

        // Step 1: the fields, before the form validates its codes against them.
        $this->seedCategoryAttributes($root, SoaCatalogue::WORK_ORDER_ATTRIBUTES, AttributeContext::WorkOrder);

        // Step 2: the form, unless one is already configured.
        if ($this->layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All) !== null) {
            return;
        }

        $this->layouts->upsert($root, AttributeContext::WorkOrder, LayoutFormScope::All, ['sections' => $this->sections()]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(): array
    {
        return array_map(
            fn (array $definition, int $index): array => $this->layoutSection($definition[0], $definition[1], $definition[2], $index, $definition[3]),
            SoaCatalogue::WORK_ORDER_SECTIONS,
            array_keys(SoaCatalogue::WORK_ORDER_SECTIONS),
        );
    }
}
