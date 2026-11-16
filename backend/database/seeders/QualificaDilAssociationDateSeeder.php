<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\LayoutItemWidth;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot for an installation seeded before "DIL" got "Data associazione"
 * (task 427520): `php artisan db:seed --class=QualificaDilAssociationDateSeeder --force`.
 * A fresh installation gets the same through QualificaCatalogSeeder.
 *
 * Touches "DIL" alone ("DIL - Lombardia" inherits from it) and only ADDS: the
 * existing `stato_assoc_cpi` attribute (looked up by code, never duplicated)
 * assigned in the Offerta context, then its item in the "Dati Lavorazione
 * Contatto" section of DIL's Offerta layout when that layout lacks it. The rest
 * of the layout is left exactly as it is, hand edits included. Re-running it
 * changes nothing.
 */
class QualificaDilAssociationDateSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    private const string SECTION_ID = 'contact-processing';

    public function __construct(private readonly AttributeLayoutService $layouts) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();

            // Step 1: the attribute, assigned on DIL itself (its barrier cuts the Formazione one).
            $this->seedCategoryAttributes($dil, [ContactProcessingAttributeCatalogue::ASSOCIATION_DATE], AttributeContext::Quote);

            // Step 2: its item, added to the layout DIL already has.
            $this->addItemToLayout($dil);
        });
    }

    private function addItemToLayout(ProductCategory $dil): void
    {
        $layout = $this->layouts->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);

        if ($layout === null) {
            $this->command?->warn('DIL has no Offerta layout of its own: "Data associazione" shows in "Altre informazioni".');

            return;
        }

        $code = ContactProcessingAttributeCatalogue::ASSOCIATION_DATE['code'];
        $sections = $layout['sections'] ?? [];
        $sectionKey = array_search(self::SECTION_ID, array_column($sections, 'id'), true);

        if ($sectionKey === false) {
            $this->command?->warn('DIL layout has no "contact-processing" section: "Data associazione" shows in "Altre informazioni".');

            return;
        }

        foreach ($sections as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                if (in_array($code, array_column($row['items'] ?? [], 'attribute_code'), true)) {
                    return;
                }
            }
        }

        $sections[$sectionKey]['rows'] = $this->rowsWithAssociationDate($sections[$sectionKey]['rows'] ?? [], $code);

        $this->layouts->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, [...$layout, 'sections' => $sections]);
    }

    /**
     * A row of its own right under "Residuo Ore", or at the section's end when
     * that field was moved or removed by hand.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function rowsWithAssociationDate(array $rows, string $code): array
    {
        $newRow = [
            'id' => sprintf('%s-%s', self::SECTION_ID, $code),
            'items' => [['attribute_code' => $code, 'width' => LayoutItemWidth::Half->value]],
        ];

        foreach ($rows as $index => $row) {
            if (in_array(ContactProcessingAttributeCatalogue::DIL_REMAINING_HOURS, array_column($row['items'] ?? [], 'attribute_code'), true)) {
                array_splice($rows, $index + 1, 0, [$newRow]);

                return $rows;
            }
        }

        $rows[] = $newRow;

        return $rows;
    }
}
