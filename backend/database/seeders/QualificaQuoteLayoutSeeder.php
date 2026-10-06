<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Enums\LayoutSectionVariant;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\RetiresAttributes;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\QualificaCatalog\AplPracticeCatalogue;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Database\Seeders\QualificaCatalog\CourseDataAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ECampusAttributeCatalogue;
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
 * "Autofinanziato" keeps the fields the rest of the branch does not have.
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
    use RetiresAttributes;
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
     * @var list<array{0: string, 1: string, 2: list<list<string>>, 3?: array{variant: LayoutSectionVariant, columns: int, description: string}}>
     */
    private const array SECTIONS = [
        ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::ROWS],
        ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
        ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
        // The e-Campus form, styled (user directive 2026-10-01): it resolves
        // on the "Corsi E-Campus" branch alone, cut off the three sets above.
        ...ECampusAttributeCatalogue::SECTIONS,
    ];

    /**
     * Category name => the form composed for it INSTEAD of SECTIONS: the APL
     * practices, each cut off the APL root (AplPracticeCatalogue::FORMS says
     * why each keeps a form of its own).
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<list<string>>, 3: array{variant: LayoutSectionVariant, columns: int, description: string}}>>
     */
    private const array OWN_FORMS = AplPracticeCatalogue::FORMS;

    /**
     * The compositions PREVIOUS revisions of this seeder wrote, recognised
     * byte for byte so an installation already seeded is recomposed instead of
     * being frozen on an obsolete arrangement. Anything else — one item moved,
     * one section renamed — is a human's work and stays untouched.
     *
     * In release order: the lone "Dati Lavorazione Contatto" section, from when
     * QualificaContactProcessingSeeder owned this context; then the three
     * sections led by the training pair, before the 2026-09-10 directive
     * reversed them; then today's order but with the contact-processing rows
     * as they stood before "Sede corso" joined them; then today's sections as
     * they stood before "Qualifica Professionale" joined them — the current
     * ROWS, which a category composed against its previous effective set
     * (effectiveCodeRevisions) resolves to exactly that blob.
     *
     * The first two entries use PREVIOUS_ROWS as well: they predate that field
     * too, so composing them from the CURRENT rows would look for a blob no
     * revision ever wrote.
     *
     * @var list<list<array{0: string, 1: string, 2: list<list<string>>}>>
     */
    private const array PREVIOUS_SECTIONS = [
        [
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::PREVIOUS_ROWS],
        ],
        [
            ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
            ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::PREVIOUS_ROWS],
        ],
        [
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::PREVIOUS_ROWS],
            ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
            ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
        ],
        [
            ['contact-processing', ContactProcessingAttributeCatalogue::SECTION_TITLE, ContactProcessingAttributeCatalogue::ROWS],
            ['course-data', CourseDataAttributeCatalogue::SECTION_TITLE, CourseDataAttributeCatalogue::ROWS],
            ['classroom-data', ClassroomAttributeCatalogue::SECTION_TITLE, ClassroomAttributeCatalogue::ROWS],
        ],
    ];

    /**
     * The sections the revisions before 2026-10-05 seeded HIGHLIGHTED (grey).
     * The user directive of that day wants every seeded section white: a blob
     * that is today's form but for those sections' variant is recognised and
     * recomposed. One edited by hand in any other way stays untouched.
     *
     * @var list<string>
     */
    private const array PREVIOUSLY_HIGHLIGHTED = ['apl-internship-status', 'apprenticeship-data'];

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
     * Formazione branch, the training fields reaching it all by inheritance,
     * plus the categories with a form of their own (OWN_FORMS).
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

        // Not the Consulenza leaf: it carries no attribute since the
        // 2026-09-10 directive, so its layout would prune to nothing anyway.
        $categories = ProductCategory::query()
            ->whereIn('id', $branchIds)
            ->orWhereIn('name', array_keys(self::OWN_FORMS))
            ->get();

        // Root-first: seedLayout() asks what each category INHERITS, which is
        // only settled once every level above it has been visited.
        return $this->sortRootFirst($categories, $this->hierarchy->parentIdMap());
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
        if ($existing !== null && ! $this->isPreviousComposition($category, $existing, $effective)) {
            return;
        }

        $sections = $this->sections($category, $effective);

        // Spec 0115: a row saying exactly what the ancestor's already says is
        // 15 copies of one form to maintain. Write nothing — and drop the copy
        // an earlier revision wrote, or it would keep winning over the
        // ancestor it duplicates.
        if ($this->inheritedRendersSame($this->layouts, $category, AttributeContext::Quote, $sections)) {
            if ($existing !== null) {
                $this->layouts->upsert($category, AttributeContext::Quote, LayoutFormScope::All, null);
            }

            return;
        }

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
     * this category (see PREVIOUS_SECTIONS), composed from the codes this
     * category resolves today or resolved before its own assignments last
     * changed (SeedsAttributeLayouts::effectiveCodeRevisions).
     *
     * @param  array<string, mixed>  $blob
     * @param  list<string>  $effective
     */
    private function isPreviousComposition(ProductCategory $category, array $blob, array $effective): bool
    {
        $revisions = $this->effectiveCodeRevisions($this->hierarchy, $category, AttributeContext::Quote, $effective);

        foreach ($revisions as $codes) {
            foreach (self::PREVIOUS_SECTIONS as $composition) {
                $sections = $this->compose($composition, $codes);

                if ($sections !== [] && $this->composesAs($blob, $sections)) {
                    return true;
                }
            }
        }

        return $this->isRetiredECampusComposition($blob)
            || $this->isPreviousOwnForm($category, $blob, $effective)
            || $this->composesAs($blob, $this->highlightedAsBefore($this->sections($category, $effective)));
    }

    /**
     * Whether $blob is the form a previous revision seeded for a category
     * with a form of its own (AplPracticeCatalogue::PREVIOUS_FORMS). Composed
     * from today's codes: a field added since is simply absent from the old
     * rows, so the filter leaves exactly what was written.
     *
     * @param  array<string, mixed>  $blob
     * @param  list<string>  $effective
     */
    private function isPreviousOwnForm(ProductCategory $category, array $blob, array $effective): bool
    {
        $previous = AplPracticeCatalogue::PREVIOUS_FORMS[$category->name] ?? null;

        return $previous !== null && $this->composesAs($blob, $this->compose($previous, $effective));
    }

    /**
     * $sections with the PREVIOUSLY_HIGHLIGHTED ones grey again, as written
     * before every seeded section turned white.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function highlightedAsBefore(array $sections): array
    {
        return array_map(static function (array $section): array {
            if (in_array($section['id'], self::PREVIOUSLY_HIGHLIGHTED, true)) {
                $section['variant'] = LayoutSectionVariant::Highlighted->value;
            }

            return $section;
        }, $sections);
    }

    /**
     * Whether $blob is the e-Campus form of the 2026-10-01 revision, stripped
     * of its retired fields (ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES).
     * The strip keeps every surviving item's width, sized for the row it used
     * to share, so the blob composes as no current code set: it is matched
     * against the old form put through the same strip instead.
     *
     * @param  array<string, mixed>  $blob
     */
    private function isRetiredECampusComposition(array $blob): bool
    {
        $definitions = ECampusAttributeCatalogue::PREVIOUS_SECTIONS;
        $codes = array_merge(...array_merge(...array_column($definitions, 2)));
        $stripped = $this->withoutCodes($this->compose($definitions, $codes), ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES);

        return $this->composesAs($blob, $stripped);
    }

    /**
     * @param  list<string>  $effective
     * @return list<array<string, mixed>>
     */
    private function sections(ProductCategory $category, array $effective): array
    {
        return $this->compose(self::OWN_FORMS[$category->name] ?? self::SECTIONS, $effective);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: list<list<string>>, 3?: array{variant: LayoutSectionVariant, columns: int, description: string}}>  $definitions
     * @param  list<string>  $effective
     * @return list<array<string, mixed>>
     */
    private function compose(array $definitions, array $effective): array
    {
        $sections = [];

        foreach ($definitions as $definition) {
            [$id, $title, $rows] = $definition;
            $kept = $this->keepAllowedCodes($rows, $effective);

            if ($kept === []) {
                continue;
            }

            $sections[] = $this->layoutSection($id, $title, $kept, count($sections), $definition[3] ?? null);
        }

        return $sections;
    }
}
