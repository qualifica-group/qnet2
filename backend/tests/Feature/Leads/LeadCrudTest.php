<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("leads.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("leads.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create (AC-010/AC-011/AC-012/AC-016)
// ---------------------------------------------------------------------------

it('create: with registry_id and campaign_id only -> 201, optional fields are null (AC-010)', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertCreated();

    $leadId = $response->json('data.id');
    $this->assertDatabaseHas('leads', [
        'id' => $leadId,
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => null,
        'source_id' => null,
        'operator_id' => null,
        'notes' => null,
    ]);
});

it('create: 201, response shape matches the frozen contract', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create(['name' => 'Ada Contact']);
    $campaign = Campaign::factory()->create(['name' => 'Spring Push']);
    $site = OperationalSite::factory()->create();
    $site->addresses()->create(['line1' => 'Via Roma 1', 'is_primary' => true]);
    $source = Source::factory()->create(['name' => 'Fiera']);
    $operator = User::factory()->create(['name' => 'Marco Operator']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'operational_site_id' => $site->id,
        'source_id' => $source->id,
        'operator_id' => $operator->id,
        'notes' => 'Follow up next week',
    ])->assertCreated()
        // `primary_contacts` joined the contract when the record card gained
        // call/mail (see LeadRegistryContactsTest): empty here, this actor
        // holds no registries.view and the anagrafica has no card either.
        ->assertJsonPath('data.registry', ['id' => $registry->id, 'name' => 'Ada Contact', 'primary_contacts' => []])
        ->assertJsonPath('data.campaign.id', $campaign->id)
        ->assertJsonPath('data.campaign.name', 'Spring Push')
        ->assertJsonPath('data.operational_site.label', 'Via Roma 1')
        ->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Fiera'])
        ->assertJsonPath('data.operator', ['id' => $operator->id, 'name' => 'Marco Operator'])
        ->assertJsonPath('data.lead_status', 'associated')
        ->assertJsonPath('data.notes', 'Follow up next week');
});

it('create: missing registry_id -> 422 on that field, no row created (AC-011)', function () {
    $actor = leadUserWith(['create']);
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['campaign_id' => $campaign->id])
        ->assertStatus(422)->assertJsonValidationErrors('registry_id');

    expect(Lead::count())->toBe(0);
});

it('create: missing campaign_id -> 422 on that field, no row created (AC-011)', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['registry_id' => $registry->id])
        ->assertStatus(422)->assertJsonValidationErrors('campaign_id');

    expect(Lead::count())->toBe(0);
});

it('create: missing lead status still returns derived not-associated status', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', ['registry_id' => $registry->id, 'campaign_id' => $campaign->id])
        ->assertCreated()
        ->assertJsonPath('data.lead_status', 'not_associated');
});

it('create: 403 without leads.create, no row created (AC-012)', function () {
    $actor = leadUserWith([]);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
    ])->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('create: a non-existent campaign_id -> 422 (exists), not 500 (AC-016)', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('campaign_id');

    expect(Lead::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// update (AC-013)
// ---------------------------------------------------------------------------

it('update: PATCH with only notes -> 200, only notes changes, the FKs stay put (AC-013)', function () {
    $actor = leadUserWith(['update']);
    $lead = Lead::factory()->create(['notes' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'After');

    $this->assertDatabaseHas('leads', [
        'id' => $lead->id,
        'registry_id' => $lead->registry_id,
        'campaign_id' => $lead->campaign_id,
        'operational_site_id' => $lead->operational_site_id,
        'source_id' => $lead->source_id,
        'operator_id' => $lead->operator_id,
        'notes' => 'After',
    ]);
});

// ---------------------------------------------------------------------------
// show/delete authz (AC-014/AC-015)
// ---------------------------------------------------------------------------

it('show: 403 without leads.view (AC-014)', function () {
    $actor = leadUserWith([]);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent lead', function () {
    $actor = leadUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/leads/999999')->assertNotFound();
});

it('delete: 403 without leads.delete (AC-014)', function () {
    $actor = leadUserWith([]);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$target->id}")->assertForbidden();
});

it('delete: 204, removes the lead (AC-015)', function () {
    $actor = leadUserWith(['delete']);
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/leads/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('leads', ['id' => $target->id]);
});

it('delete: 404 for a non-existent lead', function () {
    $actor = leadUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/leads/999999')->assertNotFound();
});
