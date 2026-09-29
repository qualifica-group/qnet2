<?php

use App\Models\Campaign;
use App\Models\Lead;
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
// AC-014 (BE) — StoreLeadRequest/UpdateLeadRequest accept extra_fields;
// manual create/update persist it; LeadResource exposes it
// ---------------------------------------------------------------------------

it('create: extra_fields is persisted and exposed by LeadResource', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'source_id' => Source::factory()->create()->id,
        'extra_fields' => ['Origine' => 'Fiera Milano', 'Note' => 'VIP'],
    ])->assertCreated()
        ->assertJsonPath('data.extra_fields', ['Origine' => 'Fiera Milano', 'Note' => 'VIP']);

    $leadId = $response->json('data.id');
    $this->assertDatabaseHas('leads', ['id' => $leadId]);
    expect(Lead::query()->findOrFail($leadId)->extra_fields)->toBe(['Origine' => 'Fiera Milano', 'Note' => 'VIP']);
});

it('create: without extra_fields, it stays null', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'source_id' => Source::factory()->create()->id,
    ])->assertCreated()
        ->assertJsonPath('data.extra_fields', null);

    expect(Lead::query()->findOrFail($response->json('data.id'))->extra_fields)->toBeNull();
});

it('create: extra_fields must be an array of strings -> 422', function () {
    $actor = leadUserWith(['create']);
    $registry = Registry::factory()->create();
    $campaign = Campaign::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/leads', [
        'registry_id' => $registry->id,
        'campaign_id' => $campaign->id,
        'source_id' => Source::factory()->create()->id,
        'extra_fields' => ['Origine' => ['not', 'a', 'string']],
    ])->assertStatus(422)->assertJsonValidationErrors('extra_fields.Origine');

    expect(Lead::count())->toBe(0);
});

it('update: PATCH with extra_fields persists and exposes it', function () {
    $actor = leadUserWith(['update']);
    $lead = Lead::factory()->create(['extra_fields' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['extra_fields' => ['Campagna esterna' => 'Sì']])
        ->assertOk()
        ->assertJsonPath('data.extra_fields', ['Campagna esterna' => 'Sì']);

    expect($lead->fresh()->extra_fields)->toBe(['Campagna esterna' => 'Sì']);
});

it('update: PATCH without extra_fields leaves it untouched (submitted-flag semantics)', function () {
    $actor = leadUserWith(['update']);
    $lead = Lead::factory()->create(['extra_fields' => ['Origine' => 'Fiera Milano']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'Updated notes only'])
        ->assertOk()
        ->assertJsonPath('data.extra_fields', ['Origine' => 'Fiera Milano']);

    expect($lead->fresh()->extra_fields)->toBe(['Origine' => 'Fiera Milano']);
});

it('update: PATCH with extra_fields: null clears it', function () {
    $actor = leadUserWith(['update']);
    $lead = Lead::factory()->create(['extra_fields' => ['Origine' => 'Fiera Milano']]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$lead->id}", ['extra_fields' => null])
        ->assertOk()
        ->assertJsonPath('data.extra_fields', null);

    expect($lead->fresh()->extra_fields)->toBeNull();
});

it('show: LeadResource exposes extra_fields', function () {
    $actor = leadUserWith(['view']);
    $lead = Lead::factory()->create(['extra_fields' => ['Origine' => 'Fiera Milano']]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/leads/{$lead->id}")
        ->assertOk()
        ->assertJsonPath('data.extra_fields', ['Origine' => 'Fiera Milano']);
});
