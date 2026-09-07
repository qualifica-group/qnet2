<?php

use App\Models\EmploymentProfile;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Spec 0048 (B): POST /api/leads/assign-operators — bulk-assign a Sede and
 * an Operatore to many REAL leads at once, either to one chosen operator
 * (mode=single) or load-balanced across the Sede's operators
 * (mode=balanced, business-rule br-balanced).
 */
if (! function_exists('leadAssignActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadAssignActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('leadAssignOperatorAtSite')) {
    /**
     * An operator whose employment profile holds $site on the
     * `employment_profile_operational_site` pivot, PHYSICAL by default or
     * REMOTE when $isPrimary is false (spec 0103, D-1).
     *
     * Built off EmploymentProfileFactory::raw() with the still-present
     * `operational_site_id` key stripped (that dead column no longer exists
     * on the table; the factory's own cleanup is microtask M11, out of this
     * lane's scope) instead of the usual factory()->create(), which would
     * otherwise force-fill that key straight into the insert.
     */
    function leadAssignOperatorAtSite(OperationalSite $site, bool $isPrimary = true): User
    {
        $operator = User::factory()->create();
        $employment = EmploymentProfile::query()->create(
            Arr::except(EmploymentProfile::factory()->raw(['user_id' => $operator->id]), ['operational_site_id'])
        );
        $employment->operationalSites()->attach($site->id, ['is_primary' => $isPrimary]);

        return $operator;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — mode=single
// ---------------------------------------------------------------------------

it('AC-010: mode=single assigns operator_id and operational_site_id to every targeted lead', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = leadAssignOperatorAtSite($site);
    $lead1 = Lead::factory()->create();
    $lead2 = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead1->id, $lead2->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect($lead1->fresh()->operator_id)->toBe($operator->id)
        ->and($lead1->fresh()->operational_site_id)->toBe($site->id)
        ->and($lead2->fresh()->operator_id)->toBe($operator->id)
        ->and($lead2->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-011 — mode=balanced
// ---------------------------------------------------------------------------

it('AC-011: mode=balanced spreads leads evenly across the Sede\'s operators when loads start equal', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operatorA = leadAssignOperatorAtSite($site);
    $operatorB = leadAssignOperatorAtSite($site);
    $leads = Lead::factory()->count(4)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $leads->pluck('id')->all(),
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 4);

    $countA = Lead::where('operator_id', $operatorA->id)->count();
    $countB = Lead::where('operator_id', $operatorB->id)->count();

    expect($countA + $countB)->toBe(4)
        ->and(abs($countA - $countB))->toBeLessThanOrEqual(1);
    foreach ($leads as $lead) {
        expect($lead->fresh()->operational_site_id)->toBe($site->id);
    }
});

it('AC-011: mode=balanced respects pre-existing load, filling the least-loaded operator first', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $busyOperator = leadAssignOperatorAtSite($site);
    $idleOperator = leadAssignOperatorAtSite($site);
    Lead::factory()->count(3)->create(['operator_id' => $busyOperator->id]);
    $newLeads = Lead::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => $newLeads->pluck('id')->all(),
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    // Both new leads go to the idle operator (0 vs 3): final load 3 vs 2,
    // difference <= 1 as required by br-balanced.
    expect(Lead::where('operator_id', $idleOperator->id)->count())->toBe(2)
        ->and(Lead::where('operator_id', $busyOperator->id)->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// AC-012 — zero-operator Sede
// ---------------------------------------------------------------------------

it('AC-012: mode=balanced on a Sede with zero operators is 422 and modifies nothing', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertStatus(422);

    expect($lead->fresh()->operator_id)->toBeNull()
        ->and($lead->fresh()->operational_site_id)->toBeNull();
});

it('AC-012/D-1: mode=balanced on a Sede whose only operator has it as REMOTE distributes leads, not 422', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $remoteOperator = leadAssignOperatorAtSite($site, isPrimary: false);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($lead->fresh()->operator_id)->toBe($remoteOperator->id)
        ->and($lead->fresh()->operational_site_id)->toBe($site->id);
});

// ---------------------------------------------------------------------------
// AC-013 — authz + validation
// ---------------------------------------------------------------------------

it('AC-013: 403 without leads.update, nothing modified', function () {
    $actor = leadAssignActor([]);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = leadAssignOperatorAtSite($site);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertForbidden();

    expect($lead->fresh()->operator_id)->toBeNull();
});

it('AC-013: 422 when lead_ids is empty', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => User::factory()->create()->id,
    ])->assertStatus(422)->assertJsonValidationErrors('lead_ids');
});

it('AC-013: 422 when operational_site_id does not exist', function () {
    $actor = leadAssignActor(['update']);
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => 999999,
        'mode' => 'single',
        'operator_id' => User::factory()->create()->id,
    ])->assertStatus(422)->assertJsonValidationErrors('operational_site_id');
});

it('AC-013: 422 when mode=single and operator_id is missing', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
    ])->assertStatus(422)->assertJsonValidationErrors('operator_id');
});

it('AC-013: 422 when mode is not single or balanced', function () {
    $actor = leadAssignActor(['update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads/assign-operators', [
        'lead_ids' => [$lead->id],
        'operational_site_id' => $site->id,
        'mode' => 'nonsense',
    ])->assertStatus(422)->assertJsonValidationErrors('mode');
});
