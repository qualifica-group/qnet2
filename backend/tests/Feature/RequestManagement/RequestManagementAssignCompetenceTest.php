<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0110, AC-024 (Gestione richieste half) — POST
 * /api/request-management/assign-operators narrows `mode=balanced` by
 * competence like the other two surfaces, while keeping the module's own D-3
 * scope: an offer the actor cannot reach is in NEITHER `assigned` nor
 * `skipped`.
 *
 * The endpoint's permissions and its pre-0110 assignment behaviour stay
 * covered by RequestManagementBulkActionsTest.
 */
if (! function_exists('requestCompetenceActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestCompetenceActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("request-management.{$ability}");
        }

        return $actor;
    }
}

if (! function_exists('requestCompetenceOperator')) {
    /**
     * An operator employed at $site, carrying one competence row per
     * category, all paired with $function (spec 0111 D-2). Called without a
     * function the profile stays rowless: the wildcard of INV-4b.
     */
    function requestCompetenceOperator(OperationalSite $site, ?BusinessFunction $function = null, ProductCategory ...$categories): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($site);

        if ($function !== null) {
            $factory = $factory->competentIn($function, ...$categories);
        }

        $factory->create();

        return $operator;
    }
}

/**
 * An offer whose opportunity carries exactly one product line, so its
 * requirement is exactly $category (spec 0040 rev.3 pairs, INV-1). The
 * incumbent GA2 is set through the factory: `quotes.operator_id` is
 * deliberately outside #[Fillable] (spec 0087, D-9), so update() would
 * silently drop it.
 */
if (! function_exists('requestClassifiedAs')) {
    function requestClassifiedAs(BusinessFunction $function, ProductCategory $category, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator?->id]);
    }
}

// ---------------------------------------------------------------------------
// AC-024 (AC-020 on offers) — each offer reaches a competent operator.
// ---------------------------------------------------------------------------

it('0110 AC-024: mode=balanced sends every offer to an operator competent for its product lines', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $salesFunction = BusinessFunction::factory()->create();
    $serviceFunction = BusinessFunction::factory()->create();
    $salesCategory = ProductCategory::factory()->create(['business_function_id' => $salesFunction->id]);
    $serviceCategory = ProductCategory::factory()->create(['business_function_id' => $serviceFunction->id]);

    $firstSalesOperator = requestCompetenceOperator($site, $salesFunction, $salesCategory);
    $secondSalesOperator = requestCompetenceOperator($site, $salesFunction, $salesCategory);
    $serviceOperator = requestCompetenceOperator($site, $serviceFunction, $serviceCategory);

    $salesRequestOne = requestClassifiedAs($salesFunction, $salesCategory);
    $salesRequestTwo = requestClassifiedAs($salesFunction, $salesCategory);
    $serviceRequest = requestClassifiedAs($serviceFunction, $serviceCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$salesRequestOne->id, $salesRequestTwo->id, $serviceRequest->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 3)
        ->assertJsonPath('data.skipped', 0);

    expect($salesRequestOne->fresh()->operator_id)->toBe($firstSalesOperator->id)
        ->and($salesRequestTwo->fresh()->operator_id)->toBe($secondSalesOperator->id)
        ->and($serviceRequest->fresh()->operator_id)->toBe($serviceOperator->id);
});

// ---------------------------------------------------------------------------
// AC-024 (AC-021 on offers) — the uncovered offer is skipped and, unlike the
// other two surfaces, must keep the operator it already had.
// ---------------------------------------------------------------------------

it('0110 AC-024: an offer with no competent operator is skipped, keeps its current operator and still receives the Sede', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $coveredFunction = BusinessFunction::factory()->create();
    $orphanFunction = BusinessFunction::factory()->create();
    $coveredCategory = ProductCategory::factory()->create(['business_function_id' => $coveredFunction->id]);
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $operator = requestCompetenceOperator($site, $coveredFunction, $coveredCategory);
    $previousOperator = User::factory()->create();

    $coveredRequest = requestClassifiedAs($coveredFunction, $coveredCategory);
    $orphanRequest = requestClassifiedAs($orphanFunction, $orphanCategory, $previousOperator);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$coveredRequest->id, $orphanRequest->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    // A skipped offer is NOT an offer whose operator was cleared.
    expect($coveredRequest->fresh()->operator_id)->toBe($operator->id)
        ->and($orphanRequest->fresh()->operator_id)->toBe($previousOperator->id)
        ->and($orphanRequest->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-024 (D-3 scope) — an unreachable offer counts in neither number.
// ---------------------------------------------------------------------------

it('0110 AC-024: an offer outside the actor\'s scope is in neither assigned nor skipped', function () {
    $actor = requestCompetenceActor(['viewAny', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $orphanFunction = BusinessFunction::factory()->create();
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);
    $coveredFunction = BusinessFunction::factory()->create();
    $coveredCategory = ProductCategory::factory()->create(['business_function_id' => $coveredFunction->id]);

    requestCompetenceOperator($site, $coveredFunction, $coveredCategory);

    // In scope: the actor operates it. Out of scope: nobody attached the
    // actor and they hold no `viewAll`.
    $reachableRequest = requestClassifiedAs($orphanFunction, $orphanCategory, $actor);
    $unreachableRequest = requestClassifiedAs($coveredFunction, $coveredCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$reachableRequest->id, $unreachableRequest->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 1);

    expect($unreachableRequest->fresh()->operator_id)->toBeNull()
        ->and($unreachableRequest->fresh()->operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-025 — nobody configured: the pre-feature distribution, skipped 0.
// ---------------------------------------------------------------------------

it('0110 AC-025: with no competence configured anywhere the offers distribution is the pre-feature one', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $firstOperator = requestCompetenceOperator($site);
    $secondOperator = requestCompetenceOperator($site);

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $firstRequest = requestClassifiedAs($function, $category);
    $secondRequest = requestClassifiedAs($function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstRequest->id, $secondRequest->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect($firstRequest->fresh()->operator_id)->toBe($firstOperator->id)
        ->and($secondRequest->fresh()->operator_id)->toBe($secondOperator->id);
});
