<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/leads/rows/{row} — inline RELATION cell-editing (spec
// 0054): the 5 relation columns (registry/campaign/operational_site/source/
// operator), fed by a `/for-select`-backed dropdown, declared via
// `editableField`/`relation` (D-1).

uses(RefreshDatabase::class);

if (! function_exists('leadInlineEditActor')) {
    /**
     * A direct-permission actor for `leads` only. It deliberately holds NO
     * ability on the target relation resources: since ADR 0011 was amended
     * (2026-07-31) neither the picker nor RelationValueScopeChecker consults
     * `{resource}.viewAny`, so granting them here would prove nothing.
     */
    function leadInlineEditActor(): User
    {
        foreach (['viewAny', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['leads.viewAny', 'leads.update']);

        return $user;
    }
}

if (! function_exists('leadInlineEditActorWithRole')) {
    /**
     * @param  array<string, mixed>|null  $matrixRow
     */
    function leadInlineEditActorWithRole(?array $matrixRow = null): User
    {
        foreach (['viewAny', 'update'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $role = Role::create(['name' => 'lead-inline-edit-role-'.uniqid()]);
        $role->givePermissionTo(['leads.viewAny', 'leads.update']);

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — operator
// ---------------------------------------------------------------------------

it('AC-001: PATCH `operator` picks an existing user -> 200, operator_id persisted, row re-mapped with the resolved label', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newOperator = User::factory()->create(['name' => 'Bruno Sala']);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => $newOperator->id,
    ])->assertOk();

    $response->assertJsonPath('data.id', $lead->id)
        ->assertJsonPath('data.operator.id', $newOperator->id)
        ->assertJsonPath('data.operator.name', 'Bruno Sala');

    expect($lead->fresh()->operator_id)->toBe($newOperator->id);
});

// ---------------------------------------------------------------------------
// AC-002 — the other 4 relation columns
// ---------------------------------------------------------------------------

it('AC-002: PATCH `registry` picks an existing registry -> 200, registry_id persisted', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newRegistry = Registry::factory()->create(['name' => 'Acme Spa']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'registry',
        'value' => $newRegistry->id,
    ])->assertOk()->assertJsonPath('data.registry.id', $newRegistry->id);

    expect($lead->fresh()->registry_id)->toBe($newRegistry->id);
});

it('AC-002: PATCH `campaign` picks an existing campaign -> 200, campaign_id persisted', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newCampaign = Campaign::factory()->create(['name' => 'Spring Push']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'campaign',
        'value' => $newCampaign->id,
    ])->assertOk()->assertJsonPath('data.campaign.id', $newCampaign->id);

    expect($lead->fresh()->campaign_id)->toBe($newCampaign->id);
});

it('AC-002: PATCH `operational_site` picks an existing site -> 200, operational_site_id persisted', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newSite = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operational_site',
        'value' => $newSite->id,
    ])->assertOk();

    expect($lead->fresh()->operational_site_id)->toBe($newSite->id);
});

it('AC-002: PATCH `source` picks an existing source -> 200, source_id persisted', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newSource = Source::factory()->create(['name' => 'Referral']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'source',
        'value' => $newSource->id,
    ])->assertOk()->assertJsonPath('data.source.id', $newSource->id);

    expect($lead->fresh()->source_id)->toBe($newSource->id);
});

// ---------------------------------------------------------------------------
// AC-003 — non-editable derived columns
// ---------------------------------------------------------------------------

it('AC-003: `lead_status` is not editable -> 422', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'lead_status',
        'value' => 'associated',
    ])->assertStatus(422);
});

it('AC-003: `created_at` is not editable -> 422', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'created_at',
        'value' => now()->toDateString(),
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-004 — field permission resolved on `editableField`
// ---------------------------------------------------------------------------

it('AC-004: role_field_permissions.editable=false on `operator_id` -> config emits editable:false on `operator`, PATCH -> 403', function () {
    $actor = leadInlineEditActorWithRole(
        ['resource' => 'leads', 'field' => 'operator_id', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $lead = Lead::factory()->create();
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/leads/columns')->assertOk()->json('data.columns'))->keyBy('id');
    expect($columns['operator']['editable'])->toBeFalse();

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => $newOperator->id,
    ])->assertForbidden();

    expect($lead->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-005 — nonexistent id
// ---------------------------------------------------------------------------

it('AC-005: a nonexistent user id on `operator` -> 422, no write', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => 999999999,
    ])->assertStatus(422);

    expect($lead->fresh()->operator_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-006 — an existing id the picker offers is accepted even though the actor
// holds no ability on the target module (ADR 0011 amended 2026-07-31).
// ---------------------------------------------------------------------------

it('AC-006: an existing user id is written even though the actor lacks users.viewAny', function () {
    $actor = leadInlineEditActor(); // holds leads.* only, nothing on users
    $lead = Lead::factory()->create();
    $existingUser = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => $existingUser->id,
    ])->assertOk();

    expect($lead->fresh()->operator_id)->toBe($existingUser->id);
});

// ---------------------------------------------------------------------------
// AC-007 — null handling
// ---------------------------------------------------------------------------

it('AC-007: value:null on the nullable `operator` -> 200, NULL persisted', function () {
    $actor = leadInlineEditActor();
    $operator = User::factory()->create();
    $lead = Lead::factory()->create(['operator_id' => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => null,
    ])->assertOk();

    expect($lead->fresh()->operator_id)->toBeNull();
});

it('AC-007: value:null on the non-nullable `registry` -> 422', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $originalRegistryId = $lead->registry_id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'registry',
        'value' => null,
    ])->assertStatus(422);

    expect($lead->fresh()->registry_id)->toBe($originalRegistryId);
});

// ---------------------------------------------------------------------------
// AC-012 — `note` accepted only where the column allows it (no column does yet)
// ---------------------------------------------------------------------------

it('AC-012: `note` sent on `operator` (which does not accept one) -> 422', function () {
    $actor = leadInlineEditActor();
    $lead = Lead::factory()->create();
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/leads/rows/{$lead->id}", [
        'column' => 'operator',
        'value' => $newOperator->id,
        'note' => 'Reassigned to Bruno',
    ])->assertStatus(422);

    expect($lead->fresh()->operator_id)->not->toBe($newOperator->id);
});
