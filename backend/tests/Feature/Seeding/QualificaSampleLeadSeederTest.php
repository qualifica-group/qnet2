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
        // BR-2: a linked campaign's own classification is null — the pair
        // lives on the project the conversion reads through.
        ->and($campaign->project_id)->toBe(Project::query()->sole()->getKey())
        ->and($campaign->product_category_id)->toBeNull()
        ->and(Project::query()->sole()->product_category_id)->toBe($category->getKey());
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

it('is idempotent: a second run adds nothing', function () use ($seedClassification): void {
    $seedClassification();

    test()->seed(QualificaSampleLeadSeeder::class);
    test()->seed(QualificaSampleLeadSeeder::class);

    expect(Lead::query()->count())->toBe(40)
        ->and(Registry::query()->count())->toBe(40)
        ->and(Opportunity::query()->count())->toBe(12)
        ->and(Project::query()->count())->toBe(1)
        ->and(Campaign::query()->count())->toBe(1);
});
