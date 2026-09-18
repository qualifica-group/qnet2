<?php

use App\Enums\PersonalDataTypeEnum;
use App\Enums\RequestManagementReportRowMode;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\PersonalData;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportBranch;
use App\Services\RequestManagement\Report\ReportBranchResolver;
use App\Services\RequestManagement\Report\RequestManagementReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0131 — the report branches are the product categories flagged
// `is_reportable`, each expanded to its own subtree. Spec 0141 reinstates a
// per-branch column applicability list, now DB-driven (`report_columns`,
// ReportColumnsInheritance) instead of the retired category-NAME config map:
// these tests configure it explicitly via the factory's `reportColumns()`
// state, never relying on a category's name.

uses(RefreshDatabase::class);

if (! function_exists('dynamicReportActor')) {
    function dynamicReportActor(): User
    {
        foreach (['report', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo(['request-management.report', 'request-management.viewAll']);

        return $actor;
    }
}

if (! function_exists('dynamicReportCompanyQuote')) {
    /** A request on $category whose Opportunity registry is a company created on $createdAt. */
    function dynamicReportCompanyQuote(ProductCategory $category, Carbon $createdAt): Quote
    {
        $registry = Registry::factory()->create(['created_at' => $createdAt]);
        PersonalData::factory()->company()->create([
            'personable_type' => 'registry',
            'personable_id' => $registry->id,
            'type' => PersonalDataTypeEnum::Company->value,
        ]);

        $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    }
}

it('resolves every reportable category and, under it, each subcategory in tree order with its depth (directive 2026-09-18)', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $gol = ProductCategory::factory()->childOf($formazione)->reportable()->reportColumns(['associati'])->create(['name' => 'GOL']);
    $campania = ProductCategory::factory()->childOf($gol)->reportable()->create(['name' => 'GOL - Campania']);
    $campaniaCourse = ProductCategory::factory()->childOf($campania)->create(['name' => 'Corso Campania']);
    $lombardia = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Lombardia']);
    $apl = ProductCategory::factory()->reportable()->create(['name' => 'APL']);

    $branches = app(ReportBranchResolver::class)->resolve();

    // "Formazione" is not reportable: not a branch. "GOL - Campania" is
    // reportable too, but it is reached UNDER GOL, never as a second root.
    expect(array_map(static fn (ReportBranch $branch): array => [$branch->key, $branch->label, $branch->depth], $branches))->toBe([
        [(string) $apl->id, 'APL', 0],
        [(string) $gol->id, 'GOL', 0],
        [(string) $campania->id, 'GOL - Campania', 1],
        [(string) $campaniaCourse->id, 'Corso Campania', 2],
        [(string) $lombardia->id, 'GOL - Lombardia', 1],
    ]);

    expect($branches[1]->categoryIds)->toEqualCanonicalizing([$gol->id, $campania->id, $lombardia->id, $campaniaCourse->id])
        ->and($branches[2]->categoryIds)->toEqualCanonicalizing([$campania->id, $campaniaCourse->id])
        ->and($branches[3]->categoryIds)->toBe([$campaniaCourse->id])
        ->and($branches[0]->categoryIds)->toBe([$apl->id]);

    // A subcategory with no own `report_columns` inherits its parent's.
    expect($branches[4]->categoryIdsFor('associati'))->toBe([$lombardia->id])
        ->and($branches[4]->categoryIdsFor('aziende_inserite'))->toBeNull();
});

it('drops a subcategory forced off, with its subtree, from its row and its ancestors rows (directive 2026-09-18)', function () {
    $gol = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $campania = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Campania']);
    $lombardia = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Lombardia', 'is_reportable' => false]);
    $lombardiaCourse = ProductCategory::factory()->childOf($lombardia)->create(['name' => 'Corso Lombardia']);
    $milano = ProductCategory::factory()->childOf($lombardia)->create(['name' => 'Corso Milano', 'is_reportable' => true]);

    $branches = app(ReportBranchResolver::class)->resolve();

    // Lombardia and its inheriting course are out; Milano, forced back on
    // under an excluded parent, becomes a root of its own.
    expect(array_map(static fn (ReportBranch $branch): array => [$branch->key, $branch->depth], $branches))->toBe([
        [(string) $milano->id, 0],
        [(string) $gol->id, 0],
        [(string) $campania->id, 1],
    ]);

    expect($branches[1]->categoryIds)->toEqualCanonicalizing([$gol->id, $campania->id])
        ->and($branches[1]->categoryIds)->not->toContain($lombardiaCourse->id);
});

it('a report root under a non-reportable configured parent keeps that parent columns (directive 2026-09-18)', function () {
    $apl = ProductCategory::factory()->reportColumns(['invio_presa_in_carico'])->create(['name' => 'APL']);
    $orientamento = ProductCategory::factory()->childOf($apl)->reportable()->reportColumns(null)->create(['name' => 'Orientamento Specialistico']);

    $branches = app(ReportBranchResolver::class)->resolve();

    expect(array_map(static fn (ReportBranch $branch): string => $branch->key, $branches))->toBe([(string) $orientamento->id])
        ->and($branches[0]->categoryIdsFor('invio_presa_in_carico'))->toBe([$orientamento->id])
        ->and($branches[0]->categoryIdsFor('associati'))->toBeNull();
});

it('aggregates the whole subtree on a parent row and only its own subtree on a child row', function () {
    $consulenza = ProductCategory::factory()->reportable()->create(['name' => 'Consulenza']);
    $campania = ProductCategory::factory()->childOf($consulenza)->create(['name' => 'Consulenza Campania']);
    $lombardia = ProductCategory::factory()->childOf($consulenza)->create(['name' => 'Consulenza Lombardia']);

    dynamicReportCompanyQuote($campania, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($lombardia, Carbon::parse('2026-09-11 10:00:00'));

    $rows = app(RequestManagementReportGenerator::class)->rows(
        dynamicReportActor(),
        '2026-09-01',
        '2026-09-30',
        [(string) $consulenza->id],
        RequestManagementReportRowMode::TotalOnly,
    );

    expect($rows[0]['rows'][0]->values['aziende_inserite'])->toBe(2);
});

it('is null for a column not configured for the category, and computes the real value for a configured one (spec 0141 D-3)', function () {
    $gol = ProductCategory::factory()->reportable()->reportColumns(['telefonate'])->create(['name' => 'GOL']);
    $consulenza = ProductCategory::factory()->reportable()->reportColumns(['aziende_inserite'])->create(['name' => 'Consulenza']);
    $unconfigured = ProductCategory::factory()->reportable()->reportColumns(null)->create(['name' => 'Varie']);

    dynamicReportCompanyQuote($gol, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($consulenza, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($unconfigured, Carbon::parse('2026-09-10 10:00:00'));

    $rows = app(RequestManagementReportGenerator::class)->rows(
        dynamicReportActor(),
        '2026-09-01',
        '2026-09-30',
        [(string) $gol->id, (string) $consulenza->id, (string) $unconfigured->id],
        RequestManagementReportRowMode::TotalOnly,
    );

    $companies = [];
    foreach ($rows as $pair) {
        $companies[$pair['branch']->label] = $pair['rows'][0]->values['aziende_inserite'];
    }

    // "aziende_inserite" is configured for Consulenza only; GOL and the
    // unconfigured "Varie" get null (not 0) for it.
    expect($companies)->toBe(['Consulenza' => 1, 'GOL' => null, 'Varie' => null]);
});

it('computes each overall dashboard tile only on the categories the column is active for (directive 2026-09-18)', function () {
    $gol = ProductCategory::factory()->reportable()->reportColumns(['telefonate'])->create(['name' => 'GOL']);
    $consulenza = ProductCategory::factory()->reportable()->reportColumns(['aziende_inserite'])->create(['name' => 'Consulenza']);

    dynamicReportCompanyQuote($gol, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($consulenza, Carbon::parse('2026-09-10 10:00:00'));

    Sanctum::actingAs(dynamicReportActor());

    $summary = $this->getJson('/api/request-management/report/dashboard?'.http_build_query([
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'category_keys' => [(string) $gol->id, (string) $consulenza->id],
        'row_mode' => 'total_only',
    ]))->assertOk()->json('data.summary');

    expect(collect($summary)->firstWhere('key', 'aziende_inserite')['value'])->toBe(1);
});

it('offers only reportable categories with requests in scope on the categories endpoint', function () {
    $gol = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $campania = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Campania']);
    ProductCategory::factory()->reportable()->create(['name' => 'Yisu']);
    $notReportable = ProductCategory::factory()->create(['name' => 'Consulenza']);

    dynamicReportCompanyQuote($campania, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($notReportable, Carbon::parse('2026-09-10 10:00:00'));

    Sanctum::actingAs(dynamicReportActor());

    expect($this->getJson('/api/request-management/report/categories')->assertOk()->json('data.categories'))
        ->toBe([
            ['key' => (string) $gol->id, 'label' => 'GOL', 'depth' => 0, 'parent_key' => null],
            ['key' => (string) $campania->id, 'label' => 'GOL - Campania', 'depth' => 1, 'parent_key' => (string) $gol->id],
        ]);
});

it('422s a category key that is not a reportable category', function () {
    ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $notReportable = ProductCategory::factory()->create(['name' => 'Consulenza']);

    Sanctum::actingAs(dynamicReportActor());

    $this->postJson('/api/request-management/report', [
        'date_from' => '2026-09-01',
        'date_to' => '2026-09-30',
        'category_keys' => [(string) $notReportable->id],
        'row_mode' => 'all',
        'format' => 'csv',
    ])->assertUnprocessable()->assertJsonValidationErrors('category_keys.0');
});
