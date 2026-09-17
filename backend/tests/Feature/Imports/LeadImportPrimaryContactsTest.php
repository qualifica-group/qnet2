<?php

use App\Enums\ContactTypeEnum;
use App\Enums\ImportDedupMode;
use App\Imports\LeadsImportDefinition;
use App\Imports\Support\ColumnMapper;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Spec 0139 AC-004..AC-006: the leads import writes every contact of a row as
 * the primary of its type (the "Telefono" grids read primaries only), and the
 * `mobile` field is gone — a "Cellulare" column maps onto `phone`.
 *
 * @param  array<string, mixed>  $mapped
 */
function primaryContactsLeadRow(array $mapped, ?int $duplicateOfId = null): ImportRunRow
{
    return ImportRunRow::factory()->create([
        'import_run_id' => ImportRun::factory()->create(['resource' => 'leads']),
        'mapped_values' => $mapped,
        'duplicate_of_id' => $duplicateOfId,
    ]);
}

it('AC-004: create_new stores both the email and the phone as primary', function () {
    $row = primaryContactsLeadRow([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'phone' => '3331234567',
    ]);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => Campaign::factory()->create()->id,
    ], ImportDedupMode::CreateNew->value);

    $contacts = Registry::query()->sole()->personalData->contacts;

    expect($contacts)->toHaveCount(2)
        ->and($contacts->every(fn (Contact $contact): bool => $contact->is_primary))->toBeTrue();
});

it('AC-005: update_existing overwrites a non-primary phone and makes it primary, keeping the other phone', function () {
    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create();
    $legacyPhone = Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '021111111', 'is_primary' => false]);
    $otherPhone = Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '022222222', 'is_primary' => false]);
    $row = primaryContactsLeadRow(['first_name' => 'Mario', 'last_name' => 'Rossi', 'phone' => '3331234567'], $existing->id);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => Campaign::factory()->create()->id,
    ], ImportDedupMode::UpdateExisting->value);

    expect($legacyPhone->fresh()->value)->toBe('3331234567')
        ->and($legacyPhone->fresh()->is_primary)->toBeTrue()
        ->and($otherPhone->fresh()->value)->toBe('022222222')
        ->and($otherPhone->fresh()->is_primary)->toBeFalse();
});

it('AC-005: update_existing overwrites the primary phone, not an older secondary one', function () {
    $existing = Registry::factory()->create();
    $card = PersonalData::factory()->individual()->for($existing, 'personable')->create();
    $secondary = Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '021111111', 'is_primary' => false]);
    $primary = Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '022222222', 'is_primary' => true]);
    $row = primaryContactsLeadRow(['first_name' => 'Mario', 'last_name' => 'Rossi', 'phone' => '3331234567', 'email' => 'new@example.test'], $existing->id);

    app(LeadsImportDefinition::class)->persistRow(User::factory()->create(), $row, [
        'campaign_id' => Campaign::factory()->create()->id,
    ], ImportDedupMode::UpdateExisting->value);

    $email = $card->contacts()->where('type', ContactTypeEnum::Email->value)->sole();

    expect($primary->fresh()->value)->toBe('3331234567')
        ->and($primary->fresh()->is_primary)->toBeTrue()
        ->and($secondary->fresh()->value)->toBe('021111111')
        ->and($email->is_primary)->toBeTrue();
});

it('AC-006: the leads import exposes no mobile field and maps a "Cellulare" column onto phone', function () {
    $fields = app(LeadsImportDefinition::class)->fields();

    $suggestion = (new ColumnMapper)->suggest([
        ['name' => 'Cellulare', 'index' => 0, 'duplicate' => false],
    ], $fields);

    expect(array_column($fields, 'id'))->not->toContain('mobile')
        ->and($suggestion->mapping)->toBe(['Cellulare' => 'phone']);
});
