<?php

use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Spec 0314: the opportunity's Lead is a permissionable field, hidden by the
 * production seed from every role but super-admin.
 */
function hideOpportunityLeadFor(User $actor): void
{
    $role = Role::create(['name' => 'lead-hidden']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'lead_id',
        'visible' => false,
        'editable' => false,
        'required' => false,
    ]);
    $actor->assignRole($role);
}

it('0314 AC-001: lead_id is editable on create and readonly on update', function () {
    $actor = opportunityFromLeadActor(['viewAny', 'view', 'create', 'update'], ['view']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.lead_id.visible', true)
        ->assertJsonPath('permissions.fields.lead_id.editable', true);

    $this->getJson("/api/opportunities/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.lead_id.visible', true)
        ->assertJsonPath('permissions.fields.lead_id.editable', false);
});

it('0314 AC-002: a hidden role row hides lead_id and refuses a create with a lead', function () {
    $actor = opportunityFromLeadActor(['viewAny', 'view', 'create'], ['view']);
    hideOpportunityLeadFor($actor);
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.lead_id.visible', false);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Hidden lead deal',
        'lead_id' => $lead->id,
    ], nonDerivableOpportunityFks()))->assertStatus(422)->assertJsonValidationErrors('lead_id');

    $this->assertDatabaseMissing('opportunities', ['lead_id' => $lead->id]);
});

it('0314 AC-002: super-admin sees and writes lead_id despite a hidden row', function () {
    $actor = opportunityFromLeadActor([], []);
    hideOpportunityLeadFor($actor);
    $actor->assignRole(Role::findOrCreate('super-admin'));
    $lead = completeLead();
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/opportunities')
        ->assertOk()
        ->assertJsonPath('permissions.fields.lead_id.visible', true);

    $this->postJson('/api/opportunities', array_merge([
        'name' => 'Super-admin lead deal',
        'lead_id' => $lead->id,
    ], nonDerivableOpportunityFks()))->assertCreated();
});

it('0314 AC-003: the role seeder hides lead_id on every role it syncs, never on super-admin', function () {
    Role::findOrCreate('super-admin');

    $this->seed(QualificaRoleSeeder::class);

    $roles = Role::query()->with('fieldPermissions')->get();
    $seeded = $roles->reject(fn (Role $role): bool => $role->name === 'super-admin');

    expect($seeded)->not->toBeEmpty();

    foreach ($seeded as $role) {
        $row = $role->fieldPermissions->first(fn ($row): bool => $row->resource === 'opportunities' && $row->field === 'lead_id');

        expect($row)->not->toBeNull("role {$role->name} has no lead_id row")
            ->and((bool) $row->visible)->toBeFalse()
            ->and((bool) $row->editable)->toBeFalse();
    }

    expect($roles->firstWhere('name', 'super-admin')->fieldPermissions)->toBeEmpty();
});
