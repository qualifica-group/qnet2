<?php

use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\Registry;
use Database\Seeders\QualificaSampleLeadSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The sample commercial pipeline of the Qualifica chain: one project, one
// campaign, one Anagrafica per lead, and a share of the batch already
// converted into an opportunity through the real spec 0044 path.
uses(RefreshDatabase::class);

$seedClassification = fn (): ProductCategory => ProductCategory::factory()->create([
    'business_function_id' => BusinessFunction::factory(),
]);

it('skips itself when no category derives a business function, rather than seed leads that cannot convert', function (): void {
    // A category with no business function derives no product line, so
    // ConvertLeadToOpportunity would reject every conversion (AC-012).
    ProductCategory::factory()->create(['business_function_id' => null]);

    test()->seed(QualificaSampleLeadSeeder::class);

    expect(Lead::query()->count())->toBe(0)
        ->and(Project::query()->count())->toBe(0)
        ->and(Campaign::query()->count())->toBe(0);
});

it('seeds the batch on one project/campaign, an Anagrafica per lead', function () use ($seedClassification): void {
    $category = $seedClassification();

    test()->seed(QualificaSampleLeadSeeder::class);

    $campaign = Campaign::query()->sole();

    expect(Lead::query()->count())->toBe(40)
        ->and(Registry::query()->count())->toBe(40)
        ->and(Lead::query()->where('campaign_id', $campaign->getKey())->count())->toBe(40)
        // BR-2: a linked campaign owns no rows of its own — the collection
        // lives on the project the conversion reads through. Spec 0094
        // (D-1/D-2) replaced the scalar business_function_id/
        // product_category_id columns with the productLines() collection:
        // the old columns were dropped, so reading them back would return
        // null in silence instead of failing the assertion.
        ->and($campaign->project_id)->toBe(Project::query()->sole()->getKey())
        ->and($campaign->productLines()->count())->toBe(0)
        ->and(Project::query()->sole()->productLines()->firstOrFail()->product_category_id)->toBe($category->getKey());
});

it('converts part of the batch, leaving the rest as plain leads', function () use ($seedClassification): void {
    $seedClassification();

    test()->seed(QualificaSampleLeadSeeder::class);

    // The opportunity is linked back to its lead (spec 0040, D-2) and its
    // product line is the one derived from the campaign's project.
    expect(Opportunity::query()->count())->toBe(12)
        ->and(Lead::query()->has('opportunity')->count())->toBe(12)
        ->and(Lead::query()->doesntHave('opportunity')->count())->toBe(28)
        ->and(Opportunity::query()->first()->productLines()->count())->toBe(1);
});

it('sizes its batch from the run() arguments, so one run can be made bigger', function () use ($seedClassification): void {
    // User directive 2026-09-08: `SAMPLE_LEADS`/`SAMPLE_CONVERTED_LEADS` on
    // `--leads`/`--converted-leads` of qualifica:seed-sample land here as
    // run() arguments — the alternative to launching the chain several times.
    $seedClassification();

    app(QualificaSampleLeadSeeder::class)->run(leads: 9, convertedLeads: 2);

    expect(Lead::query()->count())->toBe(9)
        ->and(Lead::query()->has('opportunity')->count())->toBe(2)
        // The batch the campaign is aiming at follows the same knob.
        ->and(Campaign::query()->sole()->target_lead)->toBe(9);
});

it('appends a second batch on re-run, reusing the one project and campaign', function () use ($seedClassification): void {
    // User directive 2026-09-08: the seeder ACCUMULATES, so it can be launched
    // again whenever more rows are wanted. Only the project/campaign pair is
    // looked up by name and reused — never duplicated per run.
    $seedClassification();

    test()->seed(QualificaSampleLeadSeeder::class);
    test()->seed(QualificaSampleLeadSeeder::class);

    expect(Lead::query()->count())->toBe(80)
        ->and(Registry::query()->count())->toBe(80)
        ->and(Opportunity::query()->count())->toBe(24)
        ->and(Project::query()->count())->toBe(1)
        ->and(Campaign::query()->count())->toBe(1);
});
