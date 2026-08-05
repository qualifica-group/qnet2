<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoOpportunitySeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Everything a VALID opportunity needs: the mandatory registry plus the demo
 * catalogue behind `product_lines`/`products_of_interest` (both min:1) and the
 * status lookup.
 */
function seedOpportunityDependencies(int $registryCount = 3): void
{
    Registry::factory()->count($registryCount)->create();
    Company::factory()->count(2)->create();
    CompanySite::factory()->count(2)->create();
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(8)->create();

    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoProductSeeder::class);
}

it('seeds standalone opportunities, some carrying more than one ordered manager', function (): void {
    seedOpportunityDependencies();

    test()->seed(DemoOpportunitySeeder::class);

    expect(Opportunity::count())->toBeGreaterThan(0);

    // At least one opportunity ends up with 2+ managers (the multi-slot path).
    $multiSlot = DB::table('opportunity_user')
        ->select('opportunity_id')
        ->groupBy('opportunity_id')
        ->havingRaw('COUNT(*) >= 2')
        ->get();

    expect($multiSlot)->not->toBeEmpty();

    // Positions are contiguous 1..n within a multi-slot deal (managerSyncMap).
    $opportunityId = $multiSlot->first()->opportunity_id;
    $positions = DB::table('opportunity_user')
        ->where('opportunity_id', $opportunityId)
        ->orderBy('position')
        ->pluck('position')
        ->all();

    expect($positions)->toBe(range(1, count($positions)));
});

it('assigns only real seeded users as managers', function (): void {
    seedOpportunityDependencies(registryCount: 2);

    test()->seed(DemoOpportunitySeeder::class);

    $userIds = User::query()->pluck('id')->all();
    $managerIds = DB::table('opportunity_user')->pluck('user_id')->unique()->all();

    expect($managerIds)->not->toBeEmpty();

    foreach ($managerIds as $managerId) {
        expect($managerId)->toBeIn($userIds);
    }
});

it('fills every field the create form makes mandatory', function (): void {
    seedOpportunityDependencies();

    test()->seed(DemoOpportunitySeeder::class);

    $opportunities = Opportunity::query()->with(['productLines', 'productsOfInterest'])->get();

    expect($opportunities)->not->toBeEmpty();

    foreach ($opportunities as $opportunity) {
        expect($opportunity->registry_id)->not->toBeNull($opportunity->name)
            // Both collections are `required|min:1` on StoreOpportunityRequest.
            ->and($opportunity->productLines)->not->toBeEmpty($opportunity->name)
            ->and($opportunity->productsOfInterest)->not->toBeEmpty($opportunity->name);

        foreach ($opportunity->productLines as $line) {
            expect($line->business_function_id)->not->toBeNull($opportunity->name)
                ->and($line->product_category_id)->not->toBeNull($opportunity->name);
        }
    }
});

it('picks products that belong to the opportunity own product lines', function (): void {
    seedOpportunityDependencies();

    test()->seed(DemoOpportunitySeeder::class);

    $hierarchy = app(CategoryHierarchy::class);

    foreach (Opportunity::query()->with(['productLines', 'productsOfInterest'])->get() as $opportunity) {
        $coveredCategoryIds = $opportunity->productLines
            ->pluck('product_category_id')
            ->flatMap(fn (int $categoryId): array => [$categoryId, ...$hierarchy->descendantIds($categoryId)])
            ->unique()
            ->all();

        foreach ($opportunity->productsOfInterest as $product) {
            expect($product->category_id)->toBeIn($coveredCategoryIds, $opportunity->name);
        }
    }
});

it('keeps every line of a deal on one business function and one branch root (spec 0077 INV-1/INV-2)', function (): void {
    // The demo catalogue has TWO roots under TWO different business
    // functions: a draw that rotated freely across them produced cards the
    // form refuses (all rows must share both).
    seedOpportunityDependencies();

    test()->seed(DemoOpportunitySeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $multiLine = 0;

    foreach (Opportunity::query()->with('productLines')->get() as $opportunity) {
        $categoryIds = $opportunity->productLines->pluck('product_category_id')->all();
        $roots = $hierarchy->rootManagementModesFor($categoryIds);

        $multiLine += count($categoryIds) > 1 ? 1 : 0;

        expect($opportunity->productLines->pluck('business_function_id')->unique())->toHaveCount(1, $opportunity->name)
            ->and(collect($roots)->pluck('root_id')->unique())->toHaveCount(1, $opportunity->name);
    }

    // The multi-line path is exercised: the assertions above are not vacuous.
    expect($multiLine)->toBeGreaterThan(0);
});

it('seeds nothing when no category pairs a business function with a product', function (): void {
    Registry::factory()->count(2)->create();
    User::factory()->count(3)->create();

    test()->seed(DemoOpportunitySeeder::class);

    // A row without product lines/products would be one the form itself
    // refuses to submit: better none at all.
    expect(Opportunity::count())->toBe(0);
});
