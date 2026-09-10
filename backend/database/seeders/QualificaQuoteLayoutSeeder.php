<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalog\CourseDataAttributeCatalogue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * The offer form of the client's catalogue (spec 0062): the LAST step of
 * QualificaCatalogSeeder, once every `quote`-context assignment is in place.
 *
 * ONE OWNER FOR THE WHOLE OFFERTA LAYOUT. Three catalogues land in the same
 * form — "Dati corso" and "Dati Aula" (moved off the product by the user
 * directive 2026-09-08) plus the "Dati Lavorazione Contatto" that
 * QualificaContactProcessingSeeder assigns — and a category holds exactly ONE
 * layout row per context. Splitting the composition across seeders would mean
 * the first one to run writes the row and the others' fields get demoted to
 * the renderer's synthesized, collapsed "other information" section. Hence the
 * division of labour: this seeder owns every `quote` layout,
 * QualificaContactProcessingSeeder owns the `work_order` ones.
 *
 * ONE ROW PER CATEGORY, not one on the root: a layout is NOT inherited the way
 * an attribute assignment is — AttributeLayoutMerger reads the layout of each
 * category CONTRIBUTING to the record (the categories of the offer lines'
 * products), never an ancestor's. A single root row would render nowhere.
 * Each category's sections are built from its OWN effective attributes, so
 * "Autofinanziato" keeps the fields the rest of the branch does not have and a
 * Consulenza leaf gets the company-appointment section alone.
 *
 * Idempotent AND non-destructive: a category whose offer layout was already
 * configured — by a previous run or by hand from the configurator — is skipped
 * entirely, never overwritten. The ONE exception is a blob a PREVIOUS revision
 * of this seeder wrote (PREVIOUS_SECTIONS): it is recognised byte for byte and
 * recomposed, or an installation already seeded would stay frozen on an
 * obsolete arrangement — the two training sets outside their sections back
 * when this seeder took the context over, and now the section order the
 * 2026-09-10 directive reversed.
 */
class QualificaQuoteLayoutSeeder extends Seeder
{
    use SeedsAttributeLayouts;

    /**
     * The form's sections, in render order: [section id, title, rows]. The
     * rows are the catalogues' own pairings, filtered per category before
     * being written.
     *
     * Order is the reading order of the request form (user directive
     * 2026-09-10): what the operator records while working the contact, then
     * what is being sold, then the classroom edition delivering it.
     *
     * @var list<array{0: string, 1: string, 2: list<list<string>>}>
     */
    private const array SECTIONS = [
        ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::ROWS],
        ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
        ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
    ];

    /**
     * The compositions PREVIOUS revisions of this seeder wrote, recognised
     * byte for byte so an installation already seeded is recomposed instead of
     * being frozen on an obsolete arrangement. Anything else — one item moved,
     * one section renamed — is a human's work and stays untouched.
     *
     * In release order: the lone "Dati Lavorazione Contatto" section, from when
     * QualificaContactProcessingSeeder owned this context; then the three
     * sections led by the training pair, before the 2026-09-10 directive
     * reversed them.
     *
     * @var list<list<array{0: string, 1: string, 2: list<list<string>>}>>
     */
    private const array PREVIOUS_SECTIONS = [
        [
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::ROWS],
        ],
        [
            ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
            ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::ROWS],
        ],
    ];

    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        foreach ($this->layoutCategories() as $category) {
            $this->seedLayout($category);
        }
    }

    /**
     * Every category an offer can be filed under for these sets: the whole
     * Formazione branch (the training fields reach it all by inheritance) plus
     * the two Consulenza leaves, which are siblings and carry their own set.
     *
     * @return Collection<int, ProductCategory>
     */
    private function layoutCategories(): Collection
    {
        $root = ProductCategory::query()
            ->where('name', ContactProcessingAttributeCatalogue::TRAINING_CATEGORY)
            ->whereNull('parent_id')
            ->firstOrFail();

        $branchIds = [$root->id, ...$this->hierarchy->descendantIds($root->id)];

        return ProductCategory::query()
            ->whereIn('id', $branchIds)
            ->orWhereIn('name', ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES)
            ->get();
    }

    private function seedLayout(ProductCategory $category): void
    {
        $effective = $this->hierarchy
            ->effectiveAttributes($category, AttributeContext::Quote)
            ->pluck('code')
            ->all();

        $existing = $this->layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All);

        // A configured layout is user data: leave it exactly as it is, unless
        // it is verbatim what the previous revision seeded.
        if ($existing !== null && ! $this->isPreviousComposition($existing, $effective)) {
            return;
        }

        $sections = $this->sections($effective);

        // A category resolving none of the three catalogues keeps the flat
        // rendering: an empty layout is a missing row, not a blob with zero
        // sections (AttributeLayoutService).
        if ($sections === []) {
            return;
        }

        $this->layouts->upsert($category, AttributeContext::Quote, LayoutFormScope::All, ['sections' => $sections]);
    }

    /**
     * Whether $blob is exactly what one of the previous revisions wrote for
     * this category, composed from the same catalogue rows this category still
     * resolves (see PREVIOUS_SECTIONS).
     *
     * @param  array<string, mixed>  $blob
     * @param  list<string>  $effective
     */
    private function isPreviousComposition(array $blob, array $effective): bool
    {
        foreach (self::PREVIOUS_SECTIONS as $composition) {
            $sections = $this->compose($composition, $effective);

            if ($sections !== [] && $blob == ['sections' => $sections]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $effective
     * @return list<array<string, mixed>>
     */
    private function sections(array $effective): array
    {
        return $this->compose(self::SECTIONS, $effective);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: list<list<string>>}>  $definitions
     * @param  list<string>  $effective
     * @return list<array<string, mixed>>
     */
    private function compose(array $definitions, array $effective): array
    {
        $sections = [];

        foreach ($definitions as [$id, $title, $rows]) {
            $kept = $this->keepAllowedCodes($rows, $effective);

            if ($kept === []) {
                continue;
            }

            $sections[] = $this->layoutSection($id, $title, $kept, count($sections));
        }

        return $sections;
    }
}
