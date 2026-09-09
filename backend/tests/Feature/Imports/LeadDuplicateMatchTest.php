<?php

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Imports\LeadsImportDefinition;
use App\Imports\Staging\StagedRowBuilder;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use App\Support\Import\StagedRowReviser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * spec 0036 — matching side: tax_code as a 4th match channel, lead-level
 * (registry+campaign) matching, and duplicate_meta staying coherent through
 * a review edit. The commit/endpoint side lives in
 * LeadDuplicateResolutionTest.
 */

// ---------------------------------------------------------------------------
// AC-001 — tax_code match (email/phone/mobile pre-existing, tax_code new)
// ---------------------------------------------------------------------------

it('AC-001: resolveDuplicateMatch matches an existing Registry by normalized tax_code', function () {
    $registry = Registry::factory()->create();
    PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => 'RSSMRA80A01H501U']);

    $match = app(LeadsImportDefinition::class)->resolveDuplicateMatch(['tax_code' => ' rssmra80a01h501u '], []);

    expect($match['id'])->toBe($registry->id)
        ->and($match['meta']['registry_id'])->toBe($registry->id)
        ->and($match['meta']['registry_name'])->toBe($registry->name)
        ->and($match['meta']['matched_on'])->toBe(['tax_code']);
});

// ---------------------------------------------------------------------------
// AC-101 (user directive 2026-09-09) — vat_number is a match channel too
// ---------------------------------------------------------------------------

it('AC-101: resolveDuplicateMatch matches an existing Registry by normalized vat_number', function () {
    $registry = Registry::factory()->create();
    PersonalData::factory()->company()->for($registry, 'personable')->create([
        'tax_code' => null,
        'vat_number' => '01234567890',
    ]);

    $match = app(LeadsImportDefinition::class)->resolveDuplicateMatch(['vat_number' => ' 01234567890 '], []);

    expect($match['id'])->toBe($registry->id)
        ->and($match['meta']['registry_id'])->toBe($registry->id)
        ->and($match['meta']['matched_on'])->toBe(['vat_number']);
});

it('AC-101: a row matching on both fiscal identifiers reports them cumulatively', function () {
    $registry = Registry::factory()->create();
    PersonalData::factory()->company()->for($registry, 'personable')->create([
        'tax_code' => 'RSSMRA80A01H501U',
        'vat_number' => '01234567890',
    ]);

    $match = app(LeadsImportDefinition::class)->resolveDuplicateMatch([
        'tax_code' => 'rssmra80a01h501u',
        'vat_number' => '01234567890',
    ], []);

    expect($match['meta']['matched_on'])->toBe(['tax_code', 'vat_number']);
});

it('AC-101: a vat_number nobody carries is not a duplicate', function () {
    $registry = Registry::factory()->create();
    PersonalData::factory()->company()->for($registry, 'personable')->create([
        'tax_code' => null,
        'vat_number' => '01234567890',
    ]);

    $match = app(LeadsImportDefinition::class)->resolveDuplicateMatch(['vat_number' => '09876543210'], []);

    expect($match['id'])->toBeNull()
        ->and($match['meta'])->toBeNull();
});

it('AC-001: staging a tax_code-matching row under the manual strategy resolves to duplicate with duplicate_meta', function () {
    $registry = Registry::factory()->create();
    PersonalData::factory()->individual()->for($registry, 'personable')->create(['tax_code' => 'RSSMRA80A01H501U']);

    $builder = new StagedRowBuilder(
        app(LeadsImportDefinition::class),
        User::factory()->create(),
        ['Codice Fiscale' => 'tax_code', 'Nome' => 'first_name', 'Cognome' => 'last_name'],
        ImportDedupMode::Manual,
    );

    $outcome = $builder->build(1, ['Codice Fiscale' => ' rssmra80a01h501u ', 'Nome' => 'Mario', 'Cognome' => 'Rossi']);

    expect($outcome->status)->toBe(ImportRowStatus::Duplicate)
        ->and($outcome->duplicateOfId)->toBe($registry->id)
        ->and($outcome->duplicateMeta['registry_id'])->toBe($registry->id)
        ->and($outcome->duplicateMeta['matched_on'])->toBe(['tax_code']);
});

// ---------------------------------------------------------------------------
// AC-002 — lead-level match: same-campaign lead surfaces, other-campaign doesn't
// ---------------------------------------------------------------------------

it('AC-002: duplicate_meta.lead_id is the lead on the run campaign, null when it only exists on another campaign', function () {
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'match@example.com']);

    $campaign = Campaign::factory()->create();
    $otherCampaign = Campaign::factory()->create();
    $lead = Lead::factory()->create(['registry_id' => $registry->id, 'campaign_id' => $campaign->id]);

    $definition = app(LeadsImportDefinition::class);

    $onCampaign = $definition->resolveDuplicateMatch(['email' => 'match@example.com'], ['campaign_id' => $campaign->id]);
    $onOtherCampaign = $definition->resolveDuplicateMatch(['email' => 'match@example.com'], ['campaign_id' => $otherCampaign->id]);

    expect($onCampaign['meta']['lead_id'])->toBe($lead->id)
        ->and($onOtherCampaign['meta']['lead_id'])->toBeNull()
        ->and($onOtherCampaign['meta']['registry_id'])->toBe($registry->id)
        ->and($onOtherCampaign['meta']['matched_on'])->toBe(['email']);
});

// ---------------------------------------------------------------------------
// AC-006 — an edit that clears the match clears duplicate_meta/resolution/status
// ---------------------------------------------------------------------------

it('AC-006: editing a duplicate row off its match clears duplicate_meta, resolution and the duplicate status', function () {
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'match3@example.com']);

    $actor = User::factory()->create();
    $mapping = ['Email' => 'email', 'Nome' => 'first_name', 'Cognome' => 'last_name'];

    $outcome = (new StagedRowBuilder(app(LeadsImportDefinition::class), $actor, $mapping, ImportDedupMode::Manual))
        ->build(1, ['Email' => 'match3@example.com', 'Nome' => 'Mario', 'Cognome' => 'Rossi']);

    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => $mapping,
        'dedup_strategy' => ImportDedupMode::Manual->value,
    ]);
    $row = ImportRunRow::factory()->for($run)->create([
        'row_number' => 1,
        'mapped_values' => $outcome->mappedValues,
        'status' => $outcome->status,
        'duplicate_of_id' => $outcome->duplicateOfId,
        'duplicate_meta' => $outcome->duplicateMeta,
        'resolution' => 'update',
    ]);
    expect($row->status)->toBe(ImportRowStatus::Duplicate)
        ->and($row->duplicate_meta)->not->toBeNull();

    $revised = app(StagedRowReviser::class)->revise(
        app(LeadsImportDefinition::class),
        User::factory()->create(),
        $row->importRun,
        $row,
        ['email' => 'no-longer-matching@example.com'],
    );

    expect($revised->status)->not->toBe(ImportRowStatus::Duplicate)
        ->and($revised->duplicate_meta)->toBeNull()
        ->and($revised->resolution)->toBeNull();
});
