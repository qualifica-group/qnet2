<?php

use App\Models\Contact;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// migrations:reimport-registry-contacts (spec 0378)
// ---------------------------------------------------------------------------

function backfillRegistry(int $legacyId, array $contacts): Registry
{
    $registry = Registry::factory()->create(['name' => "Registry {$legacyId}"]);
    $registry->forceFill(['old_id' => $legacyId])->saveQuietly();
    $card = $registry->personalData()->create(['type' => 'company', 'company_name' => "Registry {$legacyId}"]);

    foreach ($contacts as $contact) {
        $card->contacts()->create($contact);
    }

    return $registry;
}

function backfillLegacyRecord(int $legacyId, array $contacts): array
{
    return ['id' => $legacyId, 'is_private' => false, 'company_name' => "Registry {$legacyId}", ...$contacts];
}

function runContactsBackfill(bool $apply, array $items, string $output): void
{
    fakeLegacyRealign('registries', [], [], $items);

    test()->artisan('migrations:reimport-registry-contacts', array_filter(['--apply' => $apply, '--output' => $output]))
        ->assertSuccessful();
}

/**
 * @return list<array{0: string, 1: string, 2: bool}>
 */
function backfillCardContacts(Registry $registry): array
{
    return $registry->personalData()->firstOrFail()->contacts()->orderBy('id')->get()
        ->map(fn (Contact $contact): array => [$contact->type->value, $contact->value, $contact->is_primary])
        ->all();
}

it('adds only the missing contacts, keeping the existing and manual ones untouched', function () {
    $registry = backfillRegistry(40, [
        ['type' => 'email', 'value' => 'INFO@acme.test', 'is_primary' => true],
        ['type' => 'phone', 'value' => '081 1111111', 'label' => 'Manuale', 'is_primary' => true],
    ]);
    Activity::query()->delete();

    runContactsBackfill(true, [backfillLegacyRecord(40, [
        'email' => 'info@acme.test', 'pec' => 'acme@pec.test', 'phone' => '081 5435163', 'phone2' => '081 5434440',
        'extra_contacts' => [['type' => 'phone', 'value' => '0645436579', 'label' => 'SARA']],
    ])], legacyRealignOutputPath());

    expect(backfillCardContacts($registry))->toBe([
        ['email', 'INFO@acme.test', true],
        ['phone', '081 1111111', true],
        ['pec', 'acme@pec.test', true],
        ['phone', '081 5435163', false],
        ['phone', '081 5434440', false],
        ['phone', '0645436579', false],
    ])
        ->and(Contact::query()->where('value', '0645436579')->value('label'))->toBe('SARA')
        ->and(Activity::query()->count())->toBe(0);
});

it('writes nothing in simulation and lists in the report what it would add', function () {
    $registry = backfillRegistry(41, [['type' => 'email', 'value' => 'info@beta.test', 'is_primary' => true]]);
    $path = legacyRealignOutputPath();

    runContactsBackfill(false, [backfillLegacyRecord(41, ['email' => 'info@beta.test', 'phone' => '081 5435163'])], $path);

    $sheets = readLegacyRealignReport($path);

    expect(backfillCardContacts($registry))->toBe([['email', 'info@beta.test', true]])
        ->and($sheets['Contatti aggiunti'])->toHaveCount(2)
        ->and($sheets['Contatti aggiunti'][1])->toBe([41, $registry->id, 'Registry 41', 'phone', '081 5435163', 'Telefono', true]);
});

it('adds nothing on a second run', function () {
    $registry = backfillRegistry(42, []);
    $record = backfillLegacyRecord(42, ['email' => 'info@gamma.test', 'fax' => '081 2222222']);

    runContactsBackfill(true, [$record], legacyRealignOutputPath());
    runContactsBackfill(true, [$record], legacyRealignOutputPath());

    expect(backfillCardContacts($registry))->toBe([['email', 'info@gamma.test', true], ['fax', '081 2222222', true]]);
});

it('skips a nameless legacy record and lists it in the report', function () {
    $registry = backfillRegistry(43, []);
    $path = legacyRealignOutputPath();

    runContactsBackfill(true, [['id' => 43, 'is_private' => false, 'company_name' => null, 'email' => 'x@delta.test']], $path);

    expect(backfillCardContacts($registry))->toBe([])
        ->and(readLegacyRealignReport($path)['Anagrafiche saltate'])->toHaveCount(2);
});
