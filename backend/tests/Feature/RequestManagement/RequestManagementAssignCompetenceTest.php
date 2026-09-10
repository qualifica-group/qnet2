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
     * function the profile stays rowless: since rev.2 (D-9) not a candidate.
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
 * An offer AT $site whose opportunity carries exactly one product line, so
 * its requirement is exactly $category (spec 0040 rev.3 pairs, INV-1). The
 * incumbent GA2 is set through the factory: `quotes.operator_id` is
 * deliberately outside #[Fillable] (spec 0087, D-9), so update() would
 * silently drop it.
 *
 * The Sede is now part of the fixture rather than of the payload (spec 0113,
 * D-4): `quotes.operational_site_id` is what scopes the offer's candidates,
 * so an offer born without one would have an empty pool by construction.
 */
if (! function_exists('requestClassifiedAs')) {
    function requestClassifiedAs(OperationalSite $site, BusinessFunction $function, ProductCategory $category, ?User $operator = null): Quote
    {
        $opportunity = Opportunity::factory()->create();

        OpportunityProductLine::factory()->create([
            'opportunity_id' => $opportunity->id,
            'business_function_id' => $function->id,
            'product_category_id' => $category->id,
        ]);

        return Quote::factory()->for($opportunity)->create([
            'operational_site_id' => $site->id,
            'operator_id' => $operator?->id,
        ]);
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

    $salesRequestOne = requestClassifiedAs($site, $salesFunction, $salesCategory);
    $salesRequestTwo = requestClassifiedAs($site, $salesFunction, $salesCategory);
    $serviceRequest = requestClassifiedAs($site, $serviceFunction, $serviceCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$salesRequestOne->id, $salesRequestTwo->id, $serviceRequest->id],
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

it('0110 AC-024: an offer with no competent operator is skipped and keeps its current operator', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $coveredFunction = BusinessFunction::factory()->create();
    $orphanFunction = BusinessFunction::factory()->create();
    $coveredCategory = ProductCategory::factory()->create(['business_function_id' => $coveredFunction->id]);
    $orphanCategory = ProductCategory::factory()->create(['business_function_id' => $orphanFunction->id]);

    $operator = requestCompetenceOperator($site, $coveredFunction, $coveredCategory);
    $previousOperator = User::factory()->create();

    $coveredRequest = requestClassifiedAs($site, $coveredFunction, $coveredCategory);
    $orphanRequest = requestClassifiedAs($site, $orphanFunction, $orphanCategory, $previousOperator);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$coveredRequest->id, $orphanRequest->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    // A skipped offer is NOT an offer whose operator was cleared. Its Sede is
    // the one it was born with: since spec 0113 the action never writes it.
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
    $reachableRequest = requestClassifiedAs($site, $orphanFunction, $orphanCategory, $actor);
    $unreachableRequest = requestClassifiedAs($site, $coveredFunction, $coveredCategory);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$reachableRequest->id, $unreachableRequest->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 1);

    // Untouched: it was never even a candidate for a write. Its Sede is the
    // fixture's, which is now the reason it would have HAD a covering
    // operator had the scope let the action reach it.
    expect($unreachableRequest->fresh()->operator_id)->toBeNull()
        ->and($unreachableRequest->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-028 rev.2 — nobody configured: nobody is a candidate. Inverts the old
// AC-025 on this surface, with RequestAssignmentService left untouched.
// ---------------------------------------------------------------------------

it('0111 AC-028 rev.2: with no competence configured anywhere no offer is assigned and all are skipped', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    requestCompetenceOperator($site);
    requestCompetenceOperator($site);

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $firstRequest = requestClassifiedAs($site, $function, $category);
    $secondRequest = requestClassifiedAs($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstRequest->id, $secondRequest->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 0)
        ->assertJsonPath('data.skipped', 2);

    // Skipped, not a 422: the Sede HAS operators, none of them competent.
    expect($firstRequest->fresh()->operator_id)->toBeNull()
        ->and($secondRequest->fresh()->operator_id)->toBeNull();
});

it('0111 AC-030: a covering operator still takes the offers, the rowless colleague at the same Sede takes none', function () {
    $actor = requestCompetenceActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();

    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $covering = requestCompetenceOperator($site, $function, $category);
    $rowless = requestCompetenceOperator($site);

    $firstRequest = requestClassifiedAs($site, $function, $category);
    $secondRequest = requestClassifiedAs($site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$firstRequest->id, $secondRequest->id],
        'mode' => 'balanced',
    ])->assertOk()
        ->assertJsonPath('data.assigned', 2)
        ->assertJsonPath('data.skipped', 0);

    expect([$firstRequest->fresh()->operator_id, $secondRequest->fresh()->operator_id])
        ->toBe([$covering->id, $covering->id])
        ->not->toContain($rowless->id);
});
