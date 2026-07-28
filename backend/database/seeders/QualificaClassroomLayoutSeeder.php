<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Illuminate\Database\Seeder;

/**
 * Groups the "Dati Aula" attributes into a form section (spec 0062) on every
 * category of the Formazione branch — the last step of QualificaCatalogSeeder,
 * which creates and assigns those attributes first.
 *
 * ONE ROW PER CATEGORY, not one on the root: unlike an attribute assignment,
 * a layout is NOT inherited (AttributeLayoutService::resolveWithFallback reads
 * the exact category, no ancestor walk), and the products live in the leaves
 * ("GOL - <Regione>", "Autofinanziato"), never on the root. A single root row
 * would render nowhere.
 *
 * Each category's layout is built from its OWN effective attributes, so a
 * subcategory carrying more than the branch does (Autofinanziato and its
 * "Modalità di svolgimento") keeps them visible: everything that is not a
 * classroom field goes into a leading "Dati corso" section, because an
 * attribute left unplaced would be demoted to the renderer's synthesized,
 * collapsed "other information" section.
 *
 * Idempotent AND non-destructive: a category whose product layout was already
 * configured — by a previous run or by hand from the configurator — is skipped
 * entirely, never overwritten.
 */
class QualificaClassroomLayoutSeeder extends Seeder
{
    use SeedsAttributeLayouts;

    /**
     * The branch root, by name — a node of QualificaCatalogSeeder::CATALOG,
     * bound by identity so a rename there breaks loudly here.
     */
    private const string ROOT_CATEGORY = 'Formazione';

    /**
     * The section hosting the branch's non-classroom attributes ("Ore
     * complessive", and "Modalità di svolgimento" under Autofinanziato).
     * User-facing, kept in its original language.
     */
    private const string COURSE_SECTION_TITLE = 'Dati corso';

    private const string COURSE_SECTION_ID = 'course-data';

    private const string CLASSROOM_SECTION_ID = 'classroom-data';

    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        // Step 1: the branch — the root plus every descendant, since a layout
        // only ever applies to the exact category it is stored on.
        $root = ProductCategory::query()
            ->where('name', self::ROOT_CATEGORY)
            ->whereNull('parent_id')
            ->first();

        if ($root === null) {
            return;
        }

        $branchIds = [$root->id, ...$this->hierarchy->descendantIds($root->id)];

        // Step 2: one layout per category, from that category's own effective
        // attributes.
        foreach (ProductCategory::query()->whereIn('id', $branchIds)->get() as $category) {
            $this->seedLayout($category);
        }
    }

    private function seedLayout(ProductCategory $category): void
    {
        // A configured layout is user data: leave it exactly as it is.
        if ($this->layouts->resolveExact($category, AttributeContext::Product, LayoutFormScope::All) !== null) {
            return;
        }

        $effective = $this->hierarchy
            ->effectiveAttributes($category, AttributeContext::Product)
            ->pluck('code')
            ->all();

        $classroomCodes = array_values(array_intersect(ClassroomAttributeCatalogue::codes(), $effective));

        // A category opting out of the branch's attributes has no classroom
        // field to group: it keeps the flat rendering.
        if ($classroomCodes === []) {
            return;
        }

        $this->layouts->upsert(
            $category,
            AttributeContext::Product,
            LayoutFormScope::All,
            ['sections' => $this->sections($classroomCodes, array_diff($effective, $classroomCodes))],
        );
    }

    /**
     * @param  list<string>  $classroomCodes
     * @param  array<int, string>  $otherCodes
     * @return list<array<string, mixed>>
     */
    private function sections(array $classroomCodes, array $otherCodes): array
    {
        $sections = [];

        if ($otherCodes !== []) {
            $sections[] = $this->layoutSection(
                self::COURSE_SECTION_ID,
                self::COURSE_SECTION_TITLE,
                array_chunk(array_values($otherCodes), self::LAYOUT_SECTION_COLUMNS),
                count($sections),
            );
        }

        $sections[] = $this->layoutSection(
            self::CLASSROOM_SECTION_ID,
            ClassroomAttributeCatalogue::SECTION_TITLE,
            $this->keepAllowedCodes(ClassroomAttributeCatalogue::ROWS, $classroomCodes),
            count($sections),
        );

        return $sections;
    }
}
