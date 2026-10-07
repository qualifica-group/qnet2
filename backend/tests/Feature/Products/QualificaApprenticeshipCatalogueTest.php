<?php

use App\CustomFields\Table\TableFieldConfigValidator;
use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Models\Attribute;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflow;
use App\Models\QuoteWorkflowStatus;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\Quotes\QuoteWorkflowResolver;
use Database\Seeders\QualificaCatalog\AplInternshipAttributeCatalogue;
use Database\Seeders\QualificaCatalog\AplOrientationAttributeCatalogue;
use Database\Seeders\QualificaCatalog\ApprenticeshipAttributeCatalogue;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The apprenticeship practices (user directive 2026-10-05, "Apprendistato -
// Campi Operatore" sheet): "Formazione Apprendistato" gets its own offer
// fields, form and working states, sharing the decree fields with the APL
// internships.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return list<list<string>> the attribute codes of each resolved offer section
 */
function apprenticeshipSectionCodes(array $sections): array
{
    return array_map(
        static fn (array $section): array => array_merge(...array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        )),
        $sections,
    );
}

it('gives the apprenticeships their own offer fields, cut off the APL root', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicates.

    $apl = ProductCategory::query()->where('name', 'APL')->whereNull('parent_id')->sole();
    $category = ProductCategory::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->sole();
    $ownCodes = array_column(ApprenticeshipAttributeCatalogue::ATTRIBUTES, 'code');

    expect($category->parent_id)->toBe($apl->id)
        ->and($category->is_selectable)->toBeTrue()
        ->and($category->inherits_quote_attributes)->toBeFalse()
        ->and(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe(collect($ownCodes)->sort()->values()->all())
        // 8 practice/training fields + 11 UF x (done by us, reason, days).
        ->and($ownCodes)->toHaveCount(8 + 11 * 3);

    // One "Decreto" attribute for every APL practice that keeps it, not a copy
    // per category: the internships dropped it (user directive 2026-10-07).
    expect(Attribute::query()->where('code', 'decree_status')->sole()->categories()->pluck('name')->sort()->values()->all())
        ->toBe(collect([ApprenticeshipAttributeCatalogue::CATEGORY, AplOrientationAttributeCatalogue::CATEGORY])->sort()->values()->all());

    expect(Attribute::query()->where('code', 'company_training_capacity')->sole()->options()->count())->toBe(3)
        ->and(Attribute::query()->where('code', 'apprenticeship_y2_stage_reason')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Formazione interna aziendale', 'Svolta da altro ente accreditato', 'Non ancora svolta'])
        ->and(Attribute::query()->where('code', 'teaching_tutor')->sole()->relation_target['entity_type'])->toBe('users');

    // Every UF's training days are a valid table: a date and the hours done.
    $sessions = Attribute::query()->where('type', 'table')->where('code', 'like', 'apprenticeship_%_sessions')->get();

    expect($sessions)->toHaveCount(11);
    $sessions->each(fn (Attribute $attribute) => expect(app(TableFieldConfigValidator::class)->validate($attribute->config))->toBe([]));
});

it('lays the apprenticeship offer form out per UF, leaving the internship form alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $category = ProductCategory::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->sole();
    $sections = $layouts->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];

    expect(array_column($sections, 'title'))->toBe([
        'Dati pratica', 'Formazione',
        ...array_column(array_slice(ApprenticeshipAttributeCatalogue::SECTIONS, 2), 1),
    ])
        ->and(count($sections))->toBe(13)
        ->and(array_unique(array_column($sections, 'variant')))->toBe(['default'])
        ->and(apprenticeshipSectionCodes($sections)[0])->toBe([
            'decree_status', 'decree_id', 'teaching_tutor', 'hiring_date', 'contract_duration_months', 'contract_end_date', 'reporting_id',
        ])
        ->and(apprenticeshipSectionCodes($sections)[2])->toBe([
            'apprenticeship_y1_uf1_done_by_us', 'apprenticeship_y1_uf1_reason', 'apprenticeship_y1_uf1_sessions',
        ])
        ->and($sections[2]['description'])->toBe('Ore previste: 2 (Sicurezza sui luoghi di lavoro).');

    // The shared decree fields do not drag the apprenticeship sections onto
    // the internships, nor theirs here.
    $internship = ProductCategory::query()->where('name', AplInternshipAttributeCatalogue::CATEGORY)->sole();
    $internshipSections = $layouts->resolveWithFallback($internship, AttributeContext::Quote, FormMode::Create)['sections'];

    expect(array_column($internshipSections, 'title'))->toBe(['Testata', 'Dati tirocinio', 'Soggetto ospitante'])
        ->and(apprenticeshipSectionCodes($internshipSections))->toBe([
            ['reporting_id', 'decree_id'],
            ['internship_type', 'vacancy_code', 'inail_position_number', 'liability_policy_number', 'insurance_company', 'practice_start_date', 'practice_end_date'],
            ['host_registry'],
        ]);
});

it('gives the apprenticeships their own working states, winning over the APL branch set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = ProductCategory::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->sole();
    $workflow = QuoteWorkflow::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->with('criteria')->sole();
    $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->orderBy('sort_order')->get();

    // The sheet lists no loss: the pinned closed_lost row keeps its default.
    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD)
        ->and($workflow->criteria->first()->value_id)->toBe($category->id)
        ->and($statuses->pluck('name')->all())->toBe([
            'Attesa Abilitazione CPI', 'Inserimento Politiche attive', 'Attesa Unilav',
            'Lavorazione portale apprendistati', 'Attesa firma documenti', 'Pratica conclusa', 'Chiusa negativa',
        ])
        ->and($statuses->map(fn (QuoteWorkflowStatus $status): ?string => $status->system_key)->all())
        ->toBe(['open', null, null, null, null, 'closed_won', 'closed_lost'])
        ->and($statuses->firstWhere('name', 'Attesa Unilav')->group->value)->toBe('pending');

    // An offer on the category resolves its own set, not the "APL" branch one.
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()->create()->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    expect(app(QuoteWorkflowResolver::class)->resolve($quote->fresh())?->is($workflow))->toBeTrue();
});
