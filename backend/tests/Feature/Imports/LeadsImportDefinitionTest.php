<?php

use App\Enums\ContactTypeEnum;
use App\Enums\ImportDedupMode;
use App\Enums\PersonalDataTypeEnum;
use App\Imports\ImportRowContext;
use App\Imports\LeadsImportDefinition;
use App\Imports\Recognition\CampaignRecognizer;
use App\Imports\Recognition\GeoRecognizer;
use App\Imports\Recognition\NameSplitRecognizer;
use App\Models\Campaign;
use App\Models\City;
use App\Models\Contact;
use App\Models\Country;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\Registry;
use App\Models\Source;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A real geo chain, mirroring CompaniesImportTest::companiesImportGeoChain().
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function leadsImportGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/**
 * @param  array<string, mixed>  $mapped
 * @param  array<string, mixed>  $resolved
 * @param  array<string, mixed>  $extraValues
 */
function stagedLeadRow(array $mapped, array $resolved = [], array $extraValues = [], ?int $duplicateOfId = null): ImportRunRow
{
    return ImportRunRow::factory()->create([
        'import_run_id' => ImportRun::factory()->create(['resource' => 'leads']),
        'mapped_values' => $mapped,
        'resolved' => $resolved === [] ? null : $resolved,
        'extra_values' => $extraValues === [] ? null : $extraValues,
        'duplicate_of_id' => $duplicateOfId,
    ]);
}

// ---------------------------------------------------------------------------
// AC-011 — create_new: Registry (card+contacts+geo address) + Lead
// ---------------------------------------------------------------------------

it('AC-011: persistRow creates a Registry (card+contacts+geo address) and a Lead in the campaign (create_new)', function () {
    $geo = leadsImportGeoChain();
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();
    $source = Source::factory()->create();
    $operator = User::factory()->create();

    $row = stagedLeadRow(
        mapped: [
            'full_name' => 'Mario Rossi',
            'email' => 'Mario.Rossi@Example.com',
            'phone' => '+39 02 1234567',
            'street' => 'Via Roma 1',
            'postal_code' => '20100',
            'notes' => 'Imported lead',
        ],
        resolved: [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => $geo['province']->id,
            'city_id' => $geo['city']->id,
        ],
        extraValues: ['Origine Lead' => 'Fiera Milano'],
    );

    $definition = app(LeadsImportDefinition::class);
    $definition->persistRow($actor, $row, [
        'campaign_id' => $campaign->id,
        'source_id' => $source->id,
        'operator_id' => $operator->id,
    ], ImportDedupMode::CreateNew->value);

    $registry = Registry::query()->where('name', 'Mario Rossi')->firstOrFail();
    expect($registry->personalData->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($registry->personalData->first_name)->toBe('Mario')
        ->and($registry->personalData->last_name)->toBe('Rossi');

    $emailContact = $registry->personalData->contacts->first(fn (Contact $contact): bool => $contact->type === ContactTypeEnum::Email);
    $phoneContact = $registry->personalData->contacts->first(fn (Contact $contact): bool => $contact->type === ContactTypeEnum::Phone);
    expect($emailContact->value)->toBe('Mario.Rossi@Example.com')
        ->and($phoneContact->value)->toBe('+39 02 1234567');

    $address = $registry->personalData->addresses->first();
    expect($address->line1)->toBe('Via Roma 1')
        ->and($address->postal_code)->toBe('20100')
        ->and($address->city_id)->toBe($geo['city']->id)
        ->and($address->province_id)->toBe($geo['province']->id)
        ->and($address->state_id)->toBe($geo['state']->id)
        ->and($address->country_id)->toBe($geo['country']->id);

    $lead = Lead::query()->where('registry_id', $registry->id)->where('campaign_id', $campaign->id)->firstOrFail();
    expect($lead->source_id)->toBe($source->id)
        ->and($lead->operator_id)->toBe($operator->id)
        ->and($lead->notes)->toBe('Imported lead');
});

it('AC-011: a company-shaped row (company_name, no first/last) creates a company card', function () {
    $actor = User::factory()->create();
    $campaign = Campaign::factory()->create();

    $row = stagedLeadRow(mapped: ['company_name' => 'Acme Srl', 'email' => 'info@acme.example.com']);

    app(LeadsImportDefinition::class)->persistRow($actor, $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value);

    $registry = Registry::query()->where('name', 'Acme Srl')->firstOrFail();
    expect($registry->personalData->type)->toBe(PersonalDataTypeEnum::Company);
});

it('AC-011: an unidentifiable row (no name/company/contact) is rejected by validateRow()', function () {
    $definition = app(LeadsImportDefinition::class);

    $errors = $definition->validateRow(['notes' => 'no identity here'], new ImportRowContext(1, User::factory()->create()));

    expect($errors)->not->toBeEmpty();
});

it('AC-011: an invalid email format is rejected by validateRow()', function () {
    $definition = app(LeadsImportDefinition::class);

    $errors = $definition->validateRow(
        ['first_name' => 'Mario', 'last_name' => 'Rossi', 'email' => 'not-an-email'],
        new ImportRowContext(1, User::factory()->create()),
    );

    expect($errors)->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// AC-012 — the 4 dedup strategies + resolveDuplicate() (Registry match)
// ---------------------------------------------------------------------------

it('AC-012: resolveDuplicate matches an existing Registry by normalized email', function () {
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'match@example.com']);

    $definition = app(LeadsImportDefinition::class);

    expect($definition->resolveDuplicate(['email' => ' Match@Example.com ']))->toBe($registry->id);
});

it('AC-012: resolveDuplicate matches an existing Registry by normalized phone (formatting-insensitive)', function () {
    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($registry, 'personable')->create();
    Contact::factory()->mobile()->for($card, 'contactable')->create(['value' => '333 123 4567']);

    $definition = app(LeadsImportDefinition::class);

    expect($definition->resolveDuplicate(['mobile' => '3331234567']))->toBe($registry->id);
});

it('AC-012: resolveDuplicate returns null when nothing matches', function () {
    $definition = app(LeadsImportDefinition::class);

    expect($definition->resolveDuplicate(['email' => 'nobody@example.com']))->toBeNull();
});

it('AC-012: create_new always inserts a new Registry, even when a duplicate matches', function () {
    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);

    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'New', 'last_name' => 'Person', 'email' => 'dup@example.com']);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value);

    expect(Registry::query()->count())->toBe(2)
        ->and(Lead::query()->where('registry_id', $existing->id)->exists())->toBeFalse();
});

it('AC-012: update_existing updates the matched Registry card and preserves its OTHER contact types', function () {
    $existing = Registry::factory()->create(['name' => 'Old Name']);
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create(['first_name' => 'Old', 'last_name' => 'Name']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);
    $pec = Contact::factory()->pec()->for($card, 'contactable')->create(['value' => 'old@pec.example.com']);

    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(
        mapped: ['first_name' => 'New', 'last_name' => 'Name', 'email' => 'dup@example.com'],
        duplicateOfId: $existing->id,
    );

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::UpdateExisting->value);

    expect(Registry::query()->count())->toBe(1);

    $existing->refresh();
    expect($existing->personalData->first_name)->toBe('New')
        ->and($existing->personalData->contacts()->count())->toBe(2);

    $pec->refresh();
    expect($pec->value)->toBe('old@pec.example.com');

    Lead::query()->where('registry_id', $existing->id)->where('campaign_id', $campaign->id)->firstOrFail();
});

it('AC-012: update_existing with NO match falls back to create_new', function () {
    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'Fresh', 'last_name' => 'Contact', 'email' => 'fresh@example.com']);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::UpdateExisting->value);

    expect(Registry::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1);
});

it('AC-012: ignore never persists anything', function () {
    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'Skip', 'last_name' => 'Me', 'email' => 'skip@example.com']);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::Ignore->value);

    expect(Registry::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(0);
});

it('AC-012: manual with a resolved duplicate is defensively left untouched (parked for review)', function () {
    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);

    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'Some', 'last_name' => 'One', 'email' => 'dup@example.com'], duplicateOfId: $existing->id);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::Manual->value);

    expect(Registry::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(0);
});

it('AC-012: manual with NO duplicate creates normally', function () {
    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'No', 'last_name' => 'Collision', 'email' => 'no-collision@example.com']);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::Manual->value);

    expect(Registry::query()->count())->toBe(1)
        ->and(Lead::query()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-013 — `__extra__` columns land in leads.extra_fields, original names,
// isolated from the anagraphic
// ---------------------------------------------------------------------------

it('AC-013: extra_values are persisted verbatim on leads.extra_fields, under their original column names', function () {
    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(
        mapped: ['first_name' => 'Extra', 'last_name' => 'Fields', 'email' => 'extra@example.com'],
        extraValues: ['Origine Lead' => 'Fiera Milano', 'Note interne' => 'VIP'],
    );

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value);

    $lead = Lead::query()->latest('id')->firstOrFail();
    expect($lead->extra_fields)->toBe(['Origine Lead' => 'Fiera Milano', 'Note interne' => 'VIP']);

    $registry = $lead->registry;
    expect($registry->personalData->getAttributes())->not->toHaveKey('Origine Lead');
});

it('AC-013: no extra_values leaves leads.extra_fields null', function () {
    $campaign = Campaign::factory()->create();
    $row = stagedLeadRow(['first_name' => 'No', 'last_name' => 'Extra', 'email' => 'no-extra@example.com']);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => $campaign->id,
    ], ImportDedupMode::CreateNew->value);

    expect(Lead::query()->latest('id')->firstOrFail()->extra_fields)->toBeNull();
});

// ---------------------------------------------------------------------------
// contract shape — fields()/globalConfig()/recognizers()/dedupModes()
// ---------------------------------------------------------------------------

it('registers `leads` in config/imports.php as LeadsImportDefinition', function () {
    expect(config('imports.definitions.leads'))->toBe(LeadsImportDefinition::class);
});

it('exposes recognizers()/supportsExtraFields()/dedupModes() per the frozen contract', function () {
    $definition = app(LeadsImportDefinition::class);

    // Spec 0108: CampaignRecognizer joins the list (campaign code -> id, per
    // row) — the frozen contract changed, the assertion follows it.
    expect($definition->recognizers())->toBe([
        CampaignRecognizer::class,
        NameSplitRecognizer::class,
        GeoRecognizer::class,
    ])
        ->and($definition->supportsExtraFields())->toBeTrue()
        ->and(array_map(fn (ImportDedupMode $mode): string => $mode->value, $definition->dedupModes()))
        ->toBe(['create_new', 'update_existing', 'ignore', 'manual']);
});

it('globalConfig() requires campaign_id and exposes neither lead_status_id, project_id, operator_id nor operational_site_id', function () {
    // Operator (and, mirroring it, Operational Site) is a Review-only,
    // per-row override (spec 0045) — never a global-config field.
    $definition = app(LeadsImportDefinition::class);
    $global = collect($definition->globalConfig())->keyBy('id');

    expect($global['campaign_id']['required'])->toBeTrue()
        ->and($global['campaign_id']['for_select_resource'])->toBe('campaigns')
        ->and($global->has('lead_status_id'))->toBeFalse()
        ->and($global->has('project_id'))->toBeFalse()
        ->and($global->has('operator_id'))->toBeFalse()
        ->and($global->has('operational_site_id'))->toBeFalse()
        ->and($global['source_id']['for_select_resource'])->toBe('sources');
});

it('createRow() (legacy create-only) is unreachable for the wizard-only leads domain', function () {
    $definition = app(LeadsImportDefinition::class);

    expect(fn () => $definition->createRow(User::factory()->create(), []))
        ->toThrow(RuntimeException::class);
});
