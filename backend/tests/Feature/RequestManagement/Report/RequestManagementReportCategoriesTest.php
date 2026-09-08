<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET /api/request-management/report/categories (spec 0106 rev-2,
// data_contract_delta, AC-026/027/028/029).

uses(RefreshDatabase::class);

if (! function_exists('reportCategoryTree')) {
    /**
     * @return array<string, ProductCategory>
     */
    function reportCategoryTree(): array
    {
        $formazione = ProductCategory::factory()->create(['name' => 'Formazione']);

        return [
            'gol' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'GOL']),
            'autoimpiego' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autoimpiego']),
            'yisu' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Yisu']),
            'autofinanziato' => ProductCategory::factory()->childOf($formazione)->create(['name' => 'Autofinanziato']),
            'consulenza' => ProductCategory::factory()->create(['name' => 'Consulenza']),
            'apl' => ProductCategory::factory()->create(['name' => 'APL']),
        ];
    }
}

if (! function_exists('reportQuote')) {
    /**
     * IDENTICAL signature everywhere in this directory (see
     * RequestManagementReportJobTrapsTest.php et al.): a narrower one here
     * would silently WIN the function_exists guard on whichever file loads
     * first, and every other file's 4-arg call would drop its extra
     * arguments without error.
     */
    function reportQuote(ProductCategory $category, ?int $operatorId = null, ?int $statusId = null, ?Opportunity $opportunity = null): Quote
    {
        $opportunity ??= Opportunity::factory()->create();
        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => BusinessFunction::factory()->create()->id,
            'product_category_id' => $category->id,
        ]);

        $attributes = $statusId !== null ? ['quote_workflow_status_id' => $statusId] : [];
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, ...$attributes]);

        if ($operatorId !== null) {
            $quote->forceFill(['operator_id' => $operatorId])->save();
        }

        return $quote;
    }
}

if (! function_exists('reportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function reportActorWith(array $abilities): User
    {
        foreach (['report', 'viewAny', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-026
// ---------------------------------------------------------------------------

it('returns only the branches with at least one request in scope, key+label, in config order (AC-026)', function () {
    $categories = reportCategoryTree();
    reportQuote($categories['consulenza']);
    reportQuote($categories['apl']);
    reportQuote($categories['gol']);
    // 'autoimpiego', 'yisu', 'autofinanziato' stay empty.

    $actor = reportActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    $response->assertJsonPath('success', true);
    expect($response->json('data.categories'))->toBe([
        ['key' => 'gol', 'label' => 'GOL'],
        ['key' => 'consulenza', 'label' => 'Consulenza'],
        ['key' => 'apl', 'label' => 'APL'],
    ]);
});

it('403s without request-management.report (AC-026)', function () {
    $actor = reportActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/request-management/report/categories')->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-027 — scope respected
// ---------------------------------------------------------------------------

it('respects the actor scope: an operator of only a Consulenza request sees only that branch (AC-027)', function () {
    $categories = reportCategoryTree();
    $operator = User::factory()->create();
    reportQuote($categories['consulenza'], $operator->id);
    reportQuote($categories['gol'], User::factory()->create()->id); // someone else's

    $actor = User::factory()->create();
    Permission::findOrCreate('request-management.report');
    $actor->givePermissionTo('request-management.report');
    // Reuse the SAME account as the operator so the request is in scope.
    Sanctum::actingAs($operator->givePermissionTo('request-management.report'));

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    expect($response->json('data.categories'))->toBe([['key' => 'consulenza', 'label' => 'Consulenza']]);
});

it('returns an empty list when the actor visibility reaches nothing (AC-027)', function () {
    reportCategoryTree();
    $actor = reportActorWith(['report']); // no viewAll, no requests of their own
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    expect($response->json('data.categories'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-028 — independent of any date range
// ---------------------------------------------------------------------------

it('lists a branch whose only request falls outside any future range, all-time (AC-028)', function () {
    $categories = reportCategoryTree();
    $quote = reportQuote($categories['yisu']);
    $quote->opportunity->forceFill(['created_at' => now()->subYears(3)])->save();

    $actor = reportActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    expect(array_column($response->json('data.categories'), 'key'))->toContain('yisu');
});

// ---------------------------------------------------------------------------
// AC-029 — a request on a descendant category surfaces the branch root
// ---------------------------------------------------------------------------

it('lists GOL when the only request is classified on a descendant category (AC-029)', function () {
    $categories = reportCategoryTree();
    $child = ProductCategory::factory()->childOf($categories['gol'])->create(['name' => 'GOL - Lombardia']);
    reportQuote($child);

    $actor = reportActorWith(['report', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/request-management/report/categories')->assertOk();

    expect($response->json('data.categories'))->toBe([['key' => 'gol', 'label' => 'GOL']]);
});
