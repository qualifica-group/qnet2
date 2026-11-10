<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot for an installation seeded before "DIL" got "Dati Aula" (task
 * 427536): `php artisan db:seed --class=QualificaDilClassroomDataSeeder --force`.
 * A fresh installation gets the same through QualificaCatalogSeeder.
 *
 * Touches "DIL" alone ("DIL - Lombardia" inherits from it) and only ADDS:
 * the classroom fields it misses in the Offerta context, then the "Dati Aula"
 * section at the bottom of its Offerta layout when that layout has none. The
 * rest of the layout is left exactly as it is, hand edits included. Re-running
 * it changes nothing.
 */
class QualificaDilClassroomDataSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    private const string SECTION_ID = 'classroom-data';

    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        DB::transaction(function (): void {
            $dil = ProductCategory::query()->where('name', ContactProcessingAttributeCatalogue::DIL_CATEGORY)->firstOrFail();

            // Step 1: the classroom fields, assigned on DIL itself (its barrier cuts the Formazione ones).
            $this->seedCategoryAttributes($dil, ClassroomAttributeCatalogue::ATTRIBUTES, AttributeContext::Quote);

            // Step 2: the section, appended to the layout DIL already has.
            $this->appendClassroomSection($dil);
        });
    }

    private function appendClassroomSection(ProductCategory $dil): void
    {
        $layout = $this->layouts->resolveExact($dil, AttributeContext::Quote, LayoutFormScope::All);

        if ($layout === null) {
            $this->command?->warn('DIL has no Offerta layout of its own: the classroom fields show in "Altre informazioni".');

            return;
        }

        $sections = $layout['sections'] ?? [];

        if (in_array(self::SECTION_ID, array_column($sections, 'id'), true)) {
            return;
        }

        $effective = $this->hierarchy->effectiveAttributes($dil, AttributeContext::Quote)->pluck('code')->all();
        $sortOrder = $sections === [] ? 0 : max(array_column($sections, 'sort_order')) + 1;
        $sections[] = $this->layoutSection(
            self::SECTION_ID,
            ClassroomAttributeCatalogue::SECTION_TITLE,
            $this->keepAllowedCodes(ClassroomAttributeCatalogue::ROWS, $effective),
            $sortOrder,
        );

        $this->layouts->upsert($dil, AttributeContext::Quote, LayoutFormScope::All, [...$layout, 'sections' => $sections]);
    }
}
