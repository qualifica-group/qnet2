<?php

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Registry;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0176: the Fonte is mandatory on a lead, inherited from the campaign
// when the lead carries none of its own.

it('AC-002: create without source_id on a campaign without source -> 422 on source_id', function () {
    Sanctum::actingAs(leadConversionActor(['create'], []));

    $this->postJson('/api/leads', [
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => Campaign::factory()->create()->id,
    ])->assertStatus(422)->assertJsonValidationErrors('source_id');

    expect(Lead::count())->toBe(0);
});

it('AC-003: create without source_id inherits the campaign source', function () {
    Sanctum::actingAs(leadConversionActor(['create'], []));
    $source = Source::factory()->create(['name' => 'Fiera']);
    $campaign = Campaign::factory()->for($source)->create();

    $this->postJson('/api/leads', [
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => $campaign->id,
        'source_id' => null,
    ])->assertCreated()->assertJsonPath('data.source', ['id' => $source->id, 'name' => 'Fiera']);
});

it('AC-004: an explicit source_id wins over the campaign source', function () {
    Sanctum::actingAs(leadConversionActor(['create'], []));
    $campaign = Campaign::factory()->for(Source::factory())->create();
    $own = Source::factory()->create();

    $this->postJson('/api/leads', [
        'registry_id' => Registry::factory()->create()->id,
        'campaign_id' => $campaign->id,
        'source_id' => $own->id,
    ])->assertCreated()->assertJsonPath('data.source.id', $own->id);
});

it('AC-005: update with source_id null inherits the campaign source', function () {
    Sanctum::actingAs(leadConversionActor(['update'], []));
    $campaignSource = Source::factory()->create();
    $lead = Lead::factory()->create([
        'campaign_id' => Campaign::factory()->for($campaignSource)->create()->id,
        'source_id' => Source::factory()->create()->id,
    ]);

    $this->patchJson("/api/leads/{$lead->id}", ['source_id' => null])
        ->assertOk()->assertJsonPath('data.source.id', $campaignSource->id);
});

it('AC-005: update with source_id null on a campaign without source -> 422', function () {
    Sanctum::actingAs(leadConversionActor(['update'], []));
    $source = Source::factory()->create();
    $lead = Lead::factory()->create(['campaign_id' => Campaign::factory()->create()->id, 'source_id' => $source->id]);

    $this->patchJson("/api/leads/{$lead->id}", ['source_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors('source_id');

    expect($lead->fresh()->source_id)->toBe($source->id);
});

it('AC-005: update inherits from the NEW campaign when campaign_id changes in the same payload', function () {
    Sanctum::actingAs(leadConversionActor(['update'], []));
    $newSource = Source::factory()->create();
    $newCampaign = Campaign::factory()->for($newSource)->create();
    $lead = Lead::factory()->create(['campaign_id' => Campaign::factory()->create()->id, 'source_id' => Source::factory()->create()->id]);

    $this->patchJson("/api/leads/{$lead->id}", ['campaign_id' => $newCampaign->id, 'source_id' => null])
        ->assertOk()->assertJsonPath('data.source.id', $newSource->id);
});

it('AC-005: a partial update without source_id stays valid on a legacy lead with no source', function () {
    Sanctum::actingAs(leadConversionActor(['update'], []));
    $lead = Lead::factory()->create(['campaign_id' => Campaign::factory()->create()->id, 'source_id' => null]);

    $this->patchJson("/api/leads/{$lead->id}", ['notes' => 'Follow up'])
        ->assertOk()->assertJsonPath('data.source', null);
});
