<?php

use App\Models\BusinessFunction;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0168, AC-005..AC-010 — POST /api/{module}/assign-operators'
 * `operators_by_site`, on BOTH `request-management` and `enrollee-management`
 * (AC-010: same shared RequestAssignmentService, only the module differs).
 */
if (! function_exists('requestBalancedActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestBalancedActor(string $module, array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("{$module}.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("{$module}.{$ability}");
        }

        return $actor;
    }
}

if (! function_exists('requestBalancedOperator')) {
    /**
     * @param  array<int, OperationalSite>  $sites
     */
    function requestBalancedOperator(array $sites, BusinessFunction $function, ProductCategory $category): User
    {
        $operator = User::factory()->create();

        $factory = EmploymentProfile::factory()->for($operator)->physicalSite($sites[0]);

        if (count($sites) > 1) {
            $factory = $factory->remoteSites(...array_slice($sites, 1));
        }

        $factory->competentIn($function, $category)->create();

        return $operator;
    }
}

if (! function_exists('requestBalancedStatusId')) {
    /** The status the offer needs to be in $module's D-2 perimeter (spec 0130). */
    function requestBalancedStatusId(string $module): int
    {
        $systemKey = $module === 'enrollee-management' ? 'validated' : 'open';

        return QuoteWorkflowStatus::query()
            ->whereNull('quote_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id')
            ?? QuoteWorkflowStatus::factory()->global()->system($systemKey)->create()->id;
    }
}

if (! function_exists('requestBalancedOffer')) {
    function requestBalancedOffer(string $module, OperationalSite $site, BusinessFunction $function, ProductCategory $category, ?User $operator = null): Quote
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
            'quote_workflow_status_id' => requestBalancedStatusId($module),
        ]);
    }
}

// A single dataset of the two modules the assignment route is registered for
// (spec 0130, D-9): AC-010 is "the same rule holds on Iscritti too".
dataset('requestBalancedModules', ['request-management', 'enrollee-management']);

// ---------------------------------------------------------------------------
// AC-005 — excluding an operator narrows the pool.
// ---------------------------------------------------------------------------

it('0168 AC-005/AC-010: excluding an operator sends none of that Sede offers to them', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $excluded = requestBalancedOperator([$site], $function, $category);
    $kept = requestBalancedOperator([$site], $function, $category);

    $first = requestBalancedOffer($module, $site, $function, $category);
    $second = requestBalancedOffer($module, $site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$first->id, $second->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$kept->id]],
        ],
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($first->fresh()->operator_id)->toBe($kept->id)
        ->and($second->fresh()->operator_id)->toBe($kept->id)
        ->and([$first->fresh()->operator_id, $second->fresh()->operator_id])
        ->not->toContain($excluded->id);
})->with('requestBalancedModules');

// ---------------------------------------------------------------------------
// AC-006 — D-2: per-group selection.
// ---------------------------------------------------------------------------

it('0168 AC-006/AC-010: an operator included for one Sede and excluded for another only receives the included one', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $napoli = OperationalSite::factory()->withAddress()->create();
    $roma = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $shared = requestBalancedOperator([$napoli, $roma], $function, $category);
    $napoliOnly = requestBalancedOperator([$napoli], $function, $category);

    $napoliOffer = requestBalancedOffer($module, $napoli, $function, $category);
    $romaOffer = requestBalancedOffer($module, $roma, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$napoliOffer->id, $romaOffer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $napoli->id, 'operator_ids' => [$napoliOnly->id]],
            ['operational_site_id' => $roma->id, 'operator_ids' => [$shared->id]],
        ],
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($napoliOffer->fresh()->operator_id)->toBe($napoliOnly->id)
        ->and($romaOffer->fresh()->operator_id)->toBe($shared->id);
})->with('requestBalancedModules');

// ---------------------------------------------------------------------------
// AC-007 — a Sede absent from the list: skipped, untouched.
// ---------------------------------------------------------------------------

it('0168 AC-007/AC-010: a Sede missing from operators_by_site skips its offers and leaves them untouched', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $listed = OperationalSite::factory()->withAddress()->create();
    $unlisted = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $listedOperator = requestBalancedOperator([$listed], $function, $category);
    requestBalancedOperator([$unlisted], $function, $category);
    $incumbent = User::factory()->create();

    $listedOffer = requestBalancedOffer($module, $listed, $function, $category);
    $unlistedOffer = requestBalancedOffer($module, $unlisted, $function, $category, $incumbent);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$listedOffer->id, $unlistedOffer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $listed->id, 'operator_ids' => [$listedOperator->id]],
        ],
    ])->assertOk()
        ->assertJsonPath('data.assigned', 1)
        ->assertJsonPath('data.skipped', 1);

    expect($listedOffer->fresh()->operator_id)->toBe($listedOperator->id)
        ->and($unlistedOffer->fresh()->operator_id)->toBe($incumbent->id);
})->with('requestBalancedModules');

// ---------------------------------------------------------------------------
// AC-008 — an operator sent but not a candidate is ignored, never a 422.
// ---------------------------------------------------------------------------

it('0168 AC-008/AC-010: an operator sent for a Sede they are no candidate of is silently ignored, no 422', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $elsewhere = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    $candidate = requestBalancedOperator([$site], $function, $category);
    $notCandidate = requestBalancedOperator([$elsewhere], $function, $category);

    $offer = requestBalancedOffer($module, $site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$candidate->id, $notCandidate->id]],
        ],
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($offer->fresh()->operator_id)->toBe($candidate->id);
})->with('requestBalancedModules');

// ---------------------------------------------------------------------------
// AC-009 — validation.
// ---------------------------------------------------------------------------

it('0168 AC-009/AC-010: operators_by_site with mode=single is 422', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestBalancedOperator([$site], $function, $category);
    $offer = requestBalancedOffer($module, $site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'single',
        'operator_id' => $operator->id,
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site');
})->with('requestBalancedModules');

it('0168 AC-009/AC-010: an empty operator_ids array, a duplicated Sede and a non-existent id are 422', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestBalancedOperator([$site], $function, $category);
    $offer = requestBalancedOffer($module, $site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => []],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operator_ids');

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
            ['operational_site_id' => $site->id, 'operator_ids' => [$operator->id]],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('operators_by_site.0.operational_site_id');

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
        'operators_by_site' => [
            ['operational_site_id' => 999999, 'operator_ids' => [999999]],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['operators_by_site.0.operational_site_id', 'operators_by_site.0.operator_ids.0']);
})->with('requestBalancedModules');

it('0168 AC-009/AC-010: without operators_by_site the current unrestricted behaviour is unchanged', function (string $module) {
    $actor = requestBalancedActor($module, ['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $operator = requestBalancedOperator([$site], $function, $category);
    $offer = requestBalancedOffer($module, $site, $function, $category);
    Sanctum::actingAs($actor);

    $this->postJson("/api/{$module}/assign-operators", [
        'request_ids' => [$offer->id],
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($offer->fresh()->operator_id)->toBe($operator->id);
})->with('requestBalancedModules');
