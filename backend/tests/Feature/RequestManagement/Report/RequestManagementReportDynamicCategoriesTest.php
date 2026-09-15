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
// `is_reportable`, each expanded to its own subtree, every real indicator
// computed for every branch (no per-branch applicability list).

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

it('resolves one branch per reportable category, keyed by id, ordered by name, subtree included', function () {
    $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);
    $gol = ProductCategory::factory()->childOf($formazione)->reportable()->create(['name' => 'GOL']);
    $campania = ProductCategory::factory()->childOf($gol)->reportable()->create(['name' => 'GOL - Campania']);
    $campaniaCourse = ProductCategory::factory()->childOf($campania)->create(['name' => 'Corso Campania']);
    $lombardia = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Lombardia']);
    $apl = ProductCategory::factory()->reportable()->create(['name' => 'APL']);

    $branches = app(ReportBranchResolver::class)->resolve();

    expect(array_map(static fn (ReportBranch $branch): array => [$branch->key, $branch->label], $branches))->toBe([
        [(string) $apl->id, 'APL'],
        [(string) $gol->id, 'GOL'],
        [(string) $campania->id, 'GOL - Campania'],
    ]);

    expect($branches[1]->categoryIds)->toEqualCanonicalizing([$gol->id, $campania->id, $lombardia->id, $campaniaCourse->id])
        ->and($branches[2]->categoryIds)->toEqualCanonicalizing([$campania->id, $campaniaCourse->id])
        ->and($branches[0]->categoryIds)->toBe([$apl->id]);
});

it('aggregates the whole subtree on a parent row and only its own subtree on a child row, computing every indicator', function () {
    $gol = ProductCategory::factory()->reportable()->create(['name' => 'GOL']);
    $campania = ProductCategory::factory()->childOf($gol)->reportable()->create(['name' => 'GOL - Campania']);
    $lombardia = ProductCategory::factory()->childOf($gol)->create(['name' => 'GOL - Lombardia']);

    dynamicReportCompanyQuote($campania, Carbon::parse('2026-09-10 10:00:00'));
    dynamicReportCompanyQuote($lombardia, Carbon::parse('2026-09-11 10:00:00'));

    $rows = app(RequestManagementReportGenerator::class)->rows(
        dynamicReportActor(),
        '2026-09-01',
        '2026-09-30',
        [(string) $gol->id, (string) $campania->id],
        RequestManagementReportRowMode::TotalOnly,
    );

    $totals = [];
    foreach ($rows as $pair) {
        $totals[$pair['branch']->label] = $pair['rows'][0]->values['aziende_inserite'];
    }

    // "Aziende inserite" used to be skipped for GOL (config applicability):
    // spec 0131 computes every real indicator for every branch.
    expect($totals)->toBe(['GOL' => 2, 'GOL - Campania' => 1]);
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
        ->toBe([['key' => (string) $gol->id, 'label' => 'GOL']]);
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
