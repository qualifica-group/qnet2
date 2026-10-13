<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Enums\WorkflowStatusGroup;
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
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The APL orientation practices (user directive 2026-10-05, "Campi Misure
// APL" sheet, Orientamento/SFL GOL): "Orientamento Specialistico" gets its own
// offer fields, form and working states, sharing the decree, reporting and
// end-date fields with the other APL practices. Every seeded section is white.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('gives the orientation practices their own offer fields, cut off the APL root', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicates.

    $apl = ProductCategory::query()->where('name', 'APL')->whereNull('parent_id')->sole();
    $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();
    $ownCodes = array_column(AplOrientationAttributeCatalogue::ATTRIBUTES, 'code');

    expect($category->parent_id)->toBe($apl->id)
        ->and($category->is_selectable)->toBeTrue()
        ->and($category->inherits_quote_attributes)->toBeFalse()
        ->and(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe(collect($ownCodes)->sort()->values()->all())
        ->and($ownCodes)->toHaveCount(16);

    // One "Data fine" attribute shared with the internships, not a copy.
    expect(Attribute::query()->where('code', 'practice_end_date')->sole()->categories()->pluck('name')->sort()->values()->all())
        ->toBe(collect([AplInternshipAttributeCatalogue::CATEGORY, AplOrientationAttributeCatalogue::CATEGORY])->sort()->values()->all());

    expect(Attribute::query()->where('code', 'sfl_renewal_status')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Da rinnovare', 'Rinnovato'])
        ->and(Attribute::query()->where('code', 'orientation_measure')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Presa in carico', 'Orientamento', 'Accompagnamento'])
        ->and(Attribute::query()->where('code', 'deliverable_policies')->sole()->config)->toBe(['min' => 1, 'max' => 4])
        ->and(Attribute::query()->where('code', 'sfl_months_received')->sole()->config)->toBe(['min' => 0, 'max' => 12]);

    // "Anagrafica Utente" (user directive 2026-10-06): a link to one registry.
    $userRegistry = Attribute::query()->where('code', 'user_registry')->sole();

    expect($userRegistry->name)->toBe('Anagrafica Utente')
        ->and($userRegistry->type)->toBe('relation')
        ->and($userRegistry->relation_target)->toBe([
            'entity_type' => 'registries',
            'cardinality' => 'one',
            'for_select_resource' => 'registries',
        ]);
});

it('lays the orientation offer form out in the sheet order, every section white', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();
    $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];
    $codesBySection = array_map(
        static fn (array $section): array => array_merge(...array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        )),
        $sections,
    );

    expect(array_column($sections, 'title'))->toBe(['Testata', 'Dati pratica', 'Percorso'])
        ->and(array_column($sections, 'variant'))->toBe(['default', 'default', 'default'])
        ->and($codesBySection)->toBe([
            ['user_registry', 'sfl_renewal_status', 'decree_status', 'deliverable_policies', 'last_active_policy_date'],
            ['orientation_measure', 'sfl_months_received', 'practice_end_date', 'reporting_id', 'decree_id'],
            [
                'orientation_convocation_date', 'orientation_intake_date', 'orientation_session_date',
                'orientation_job_support_1_date', 'orientation_job_support_2_date', 'orientation_job_support_3_date',
            ],
        ]);
});

it('recomposes the orientation form the previous revision seeded, never one edited by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();
    $seeded = $layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All);

    // The blob the 2026-10-05 revision wrote: no "Anagrafica Utente" row.
    $previous = $seeded;
    array_shift($previous['sections'][0]['rows']);
    $previous['sections'][0]['description'] = 'Rinnovo SFL, decreto e politiche attive della pratica.';
    $layouts->upsert($category, AttributeContext::Quote, LayoutFormScope::All, $previous);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect($layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All))->toBe($seeded);

    // The same form renamed by hand: a human's work, left exactly as it is.
    $handEdited = $previous;
    $handEdited['sections'][0]['title'] = 'Rinominata a mano';
    $layouts->upsert($category, AttributeContext::Quote, LayoutFormScope::All, $handEdited);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect($layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All)['sections'][0]['title'])
        ->toBe('Rinominata a mano');
});

it('gives the orientation practices their own working states, winning over the APL branch set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = ProductCategory::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->sole();
    $workflow = QuoteWorkflow::query()->where('name', AplOrientationAttributeCatalogue::CATEGORY)->with('criteria')->sole();
    $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->orderBy('sort_order')->get();

    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD)
        ->and($workflow->criteria->first()->value_id)->toBe($category->id)
        ->and($statuses->pluck('name')->all())->toBe([
            'Da Richiamare', 'Attesa esito SFL/ADI', 'Attesa _ App. CPI', 'OK App. Fissato CPI', 'Attesa Documenti',
            'NO _ Non ha Requisiti', 'Non interessato/a', 'Irreperibile', 'Non pertinente - Altra regione',
            'Numero Inesistente/Errato', 'Doppione', 'Doppione già associato',
            'Da convocare', 'Convocato', 'Presa in carico', 'Monitoraggio SFL', 'Perso',
            'Fine pratica', 'Associato NO _ Altro Ente',
        ])
        ->and($statuses->filter(fn (QuoteWorkflowStatus $status): bool => $status->system_key !== null)->pluck('system_key', 'name')->all())
        ->toBe(['Da Richiamare' => 'open', 'Fine pratica' => 'closed_won', 'Associato NO _ Altro Ente' => 'closed_lost']);

    // The APL sector's states (user directive 2026-10-09): every pesca cell a
    // loss, "Da convocare" the validated hand-over to an APL operator.
    $groupOf = fn (string $name): WorkflowStatusGroup => $statuses->firstWhere('name', $name)->group;

    expect($groupOf('Attesa esito SFL/ADI'))->toBe(WorkflowStatusGroup::Open)
        ->and($groupOf('Attesa Documenti'))->toBe(WorkflowStatusGroup::Pending)
        ->and($groupOf('Doppione già associato'))->toBe(WorkflowStatusGroup::ClosedLost)
        ->and($groupOf('Da convocare'))->toBe(WorkflowStatusGroup::Validated);

    // An offer on the category resolves its own set, not the "APL" branch one.
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()->create()->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    expect(app(QuoteWorkflowResolver::class)->resolve($quote->fresh())?->is($workflow))->toBeTrue();
});

it('turns a section an earlier revision seeded grey white, never one edited by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $apprenticeship = ProductCategory::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->sole();
    $seeded = $layouts->resolveExact($apprenticeship, AttributeContext::Quote, LayoutFormScope::All);

    // The blob the previous revision wrote: today's, its first section highlighted.
    $grey = $seeded;
    $grey['sections'][0]['variant'] = 'highlighted';
    $layouts->upsert($apprenticeship, AttributeContext::Quote, LayoutFormScope::All, $grey);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect($layouts->resolveExact($apprenticeship, AttributeContext::Quote, LayoutFormScope::All))->toBe($seeded);

    // Grey AND renamed: a human's work, left exactly as it is.
    $handEdited = $grey;
    $handEdited['sections'][0]['title'] = 'Rinominata a mano';
    $layouts->upsert($apprenticeship, AttributeContext::Quote, LayoutFormScope::All, $handEdited);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    expect($layouts->resolveExact($apprenticeship, AttributeContext::Quote, LayoutFormScope::All)['sections'][0])
        ->toMatchArray(['title' => 'Rinominata a mano', 'variant' => 'highlighted']);
});
