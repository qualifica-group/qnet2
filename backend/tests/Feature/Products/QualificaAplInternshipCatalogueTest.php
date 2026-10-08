<?php

use App\Enums\AttributeContext;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
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
use Database\Seeders\QualificaCatalog\ApprenticeshipAttributeCatalogue;
use Database\Seeders\QualificaCatalog\WorkflowStatusCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaQuoteLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The APL internships (user directive 2026-10-02, "Campi Misure APL" sheet,
// revised 2026-10-07): "Tirocinio" gets its own offer fields, form and working
// states.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('gives the APL internships their own offer fields and form, cut off the APL root', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class); // re-run: no duplicates.

    $apl = ProductCategory::query()->where('name', 'APL')->whereNull('parent_id')->sole();
    $category = ProductCategory::query()->where('name', AplInternshipAttributeCatalogue::CATEGORY)->sole();
    $ownCodes = array_column(AplInternshipAttributeCatalogue::ATTRIBUTES, 'code');

    expect($category->parent_id)->toBe($apl->id)
        ->and($category->is_selectable)->toBeTrue()
        ->and($category->inherits_quote_attributes)->toBeFalse()
        ->and(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe(collect($ownCodes)->sort()->values()->all());

    expect(Attribute::query()->where('code', 'host_registry')->sole()->relation_target)
        ->toMatchArray(['entity_type' => 'registries', 'cardinality' => 'one']);

    $layouts = app(AttributeLayoutService::class);
    $sections = $layouts->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];
    $codesBySection = array_map(
        static fn (array $section): array => array_merge(...array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        )),
        $sections,
    );

    expect($layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All))->not->toBeNull()
        ->and(array_column($sections, 'title'))->toBe(['Testata', 'Dati tirocinio', 'Soggetto ospitante'])
        // Every seeded section white (user directive 2026-10-05).
        ->and(array_column($sections, 'variant'))->toBe(['default', 'default', 'default'])
        ->and($codesBySection)->toBe([
            ['reporting_id', 'decree_id'],
            ['internship_type', 'vacancy_code', 'inail_position_number', 'liability_policy_number', 'insurance_company', 'practice_start_date', 'practice_end_date'],
            ['host_registry'],
        ]);
});

it('takes the retired fields off an internship form a previous revision seeded, white or grey', function (string $variant): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $category = ProductCategory::query()->where('name', AplInternshipAttributeCatalogue::CATEGORY)->sole();
    $seeded = $layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All);

    // The 2026-10-02 revision: its three extra fields assigned, its form stored.
    foreach (['registers_status', 'decree_status', 'practice_number'] as $code) {
        $attribute = Attribute::query()->firstOrCreate(['code' => $code], ['name' => $code, 'type' => 'text']);
        $category->attributes()->attach($attribute->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
    }
    // Built the way SeedsAttributeLayouts::layoutSection() wrote it.
    $section = static fn (string $id, string $title, string $description, string $variant, int $sortOrder, array $rows): array => [
        'id' => $id, 'title' => $title, 'description' => $description, 'variant' => $variant,
        'collapsible' => false, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => $sortOrder,
        'rows' => array_map(static fn (array $codes, int $index): array => [
            'id' => "{$id}-{$index}",
            'items' => array_map(static fn (string $code): array => [
                'attribute_code' => $code, 'width' => count($codes) === 1 ? 'full' : 'half',
            ], $codes),
        ], $rows, array_keys($rows)),
    ];
    $previous = ['sections' => [
        $section('apl-internship-status', 'Stato pratica', 'Registri, decreto e periodo del tirocinio.', $variant, 0, [
            ['registers_status', 'decree_status'],
            ['practice_start_date', 'practice_end_date'],
        ]),
        $section('apl-internship-data', 'Dati pratica', 'Numero pratica e riferimenti di rendicontazione.', 'default', 1, [
            ['practice_number'],
            ['reporting_id', 'decree_id'],
        ]),
    ]];
    AttributeLayout::query()
        ->where(['product_category_id' => $category->id, 'context' => AttributeContext::Quote->value])
        ->update(['layout' => $previous]);

    test()->seed(QualificaQuoteLayoutSeeder::class);

    $apprenticeship = ProductCategory::query()->where('name', ApprenticeshipAttributeCatalogue::CATEGORY)->sole();

    expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->all())
        ->not->toContain('registers_status', 'decree_status', 'practice_number')
        ->and($layouts->resolveExact($category, AttributeContext::Quote, LayoutFormScope::All))->toBe($seeded)
        // "Decreto" stays on the practices that keep it.
        ->and(app(CategoryHierarchy::class)->effectiveAttributes($apprenticeship, AttributeContext::Quote)->pluck('code')->all())
        ->toContain('decree_status');
})->with(['default', 'highlighted']);

it('gives the APL internships their own working states, winning over the APL branch set', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = ProductCategory::query()->where('name', AplInternshipAttributeCatalogue::CATEGORY)->sole();
    $workflow = QuoteWorkflow::query()->where('name', AplInternshipAttributeCatalogue::CATEGORY)->with('criteria')->sole();
    $statuses = QuoteWorkflowStatus::query()->where('quote_workflow_id', $workflow->id)->orderBy('sort_order')->get();

    // The pinned closed rows sit last, after the custom ones in sheet order:
    // "Interrotto" is the first loss, so "Perso" stays a custom row.
    expect($workflow->criteria)->toHaveCount(1)
        ->and($workflow->criteria->first()->field)->toBe(WorkflowStatusCatalogue::DEFAULT_CRITERION_FIELD)
        ->and($workflow->criteria->first()->value_id)->toBe($category->id)
        ->and($statuses->pluck('name')->all())->toBe([
            'Produzione documentale', 'Vacancy', 'Candidatura', 'Assenso', 'Attivazione politica attiva', 'Attivo',
            'Prorogato', 'Concluso', 'Perso', 'Attestazione finale', 'Interrotto',
        ])
        ->and($statuses->map(fn (QuoteWorkflowStatus $status): ?string => $status->system_key)->all())
        ->toBe(['open', null, null, null, null, null, null, null, null, 'closed_won', 'closed_lost'])
        ->and($statuses->firstWhere('name', 'Concluso')->group->value)->toBe('pending')
        ->and($statuses->firstWhere('name', 'Perso')->group->value)->toBe('closed_lost');

    // An offer on the category resolves its own set, not the "APL" branch one.
    $product = Product::factory()->create(['category_id' => $category->id]);
    $quote = Quote::factory()->create(['opportunity_id' => Opportunity::factory()->create()->id]);
    QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id]);

    expect(app(QuoteWorkflowResolver::class)->resolve($quote->fresh())?->is($workflow))->toBeTrue();
});
