<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Brings the "ISO" offer fields and form of an installation already seeded up
 * to IsoCatalogue, with no database refresh (user directive 2026-10-30):
 * `php artisan db:seed --class=QualificaIsoQuoteFormSeeder --force`.
 *
 * QualificaQuoteLayoutSeeder never overwrites an offer form already in place,
 * so on its own a production re-seed would leave a new field (e.g. "Altro")
 * outside its section. This seeder rewrites the root's form whatever is
 * stored: a hand edit of the ISO offer form is lost, the values the offers
 * already carry are not.
 *
 * Additive on the fields (SeedsCategoryAttributes): the missing ones are
 * created and assigned, the existing ones keep their label and options.
 * One transaction: a failure leaves the previous form in place.
 */
class QualificaIsoQuoteFormSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        $root = ProductCategory::query()->where('name', IsoCatalogue::ROOT)->whereNull('parent_id')->first();

        if ($root === null) {
            $this->command?->warn('No "ISO" category: run QualificaCatalogSeeder first.');

            return;
        }

        DB::transaction(function () use ($root): void {
            // Step 1: the fields, before the form validates its codes against them.
            $this->seedCategoryAttributes($root, IsoCatalogue::QUOTE_ATTRIBUTES, AttributeContext::Quote);

            // Step 2: the form, rewritten whatever is stored.
            $this->layouts->upsert($root, AttributeContext::Quote, LayoutFormScope::All, ['sections' => $this->sections()]);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sections(): array
    {
        return array_map(
            fn (array $definition, int $index): array => $this->layoutSection($definition[0], $definition[1], $definition[2], $index, $definition[3]),
            IsoCatalogue::QUOTE_SECTIONS,
            array_keys(IsoCatalogue::QUOTE_SECTIONS),
        );
    }
}
