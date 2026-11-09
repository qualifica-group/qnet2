<?php

use App\Enums\ContactTypeEnum;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\RegistriesTarget;
use App\Models\CustomFieldDefinition;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => seedMigrationsConfig());

function realignRegistryRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 40, 'is_private' => false, 'company_name' => 'Acme Srl', 'first_name' => null, 'last_name' => null,
        'tax_code' => '01234567890', 'vat_number' => '01234567890', 'sdi_code' => 'ABC1234', 'vat_group' => null,
        'is_supplier' => false, 'is_qualified_supplier' => false, 'agreement_status' => null, 'agreement_notes' => null,
        'size_class' => null, 'employee_count' => null, 'sector_ids' => [], 'tag_ids' => [],
        'supervisor_user_id' => null, 'manager_user_ids' => [], 'email' => 'info@acme.test', 'phone' => '+39 06 1234567',
        'street' => 'Via Roma 1', 'city' => null, 'postal_code' => '00100', 'addresses' => [],
        'created_at' => '2019-03-04 10:11:12', 'updated_at' => '2026-11-01 10:00:00',
    ], $overrides);
}

function realignedRegistry(): Registry
{
    $registry = Registry::factory()->create([
        'name' => 'Acme Srl', 'is_supplier' => false, 'is_qualified_supplier' => false, 'vat_group' => null,
        'agreement_status' => null, 'agreement_notes' => null, 'size_class' => null, 'employee_count' => null, 'supervisor_id' => null,
    ]);
    $registry->forceFill(['old_id' => 40])->saveQuietly();
    $card = $registry->personalData()->create([
        'type' => 'company', 'company_name' => 'Acme Srl', 'tax_code' => '01234567890', 'vat_number' => '01234567890', 'sdi_code' => 'ABC1234',
    ]);
    $card->contacts()->create(['type' => 'email', 'value' => 'info@acme.test', 'is_primary' => true]);
    $card->contacts()->create(['type' => 'phone', 'value' => '+39 06 1234567', 'is_primary' => true]);
    $card->addresses()->create(['line1' => 'Via Roma 1', 'postal_code' => '00100', 'is_primary' => true]);
    Activity::query()->delete();

    return $registry;
}

function runRegistriesRealign(bool $apply, array $record): LegacyRealignReport
{
    fakeLegacyRealign('registries', [40], [], [$record]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(RegistriesTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

function registryPrimaryContact(Registry $registry, ContactTypeEnum $type): ?string
{
    return $registry->personalData()->first()->primaryContact($type)?->value;
}

it('aligns card, supervisor, managers, sectors and tags, leaving the commercial roles', function () {
    $registry = realignedRegistry();
    $commercial = Referent::factory()->create();
    $registry->forceFill(['commercial_id' => $commercial->id])->saveQuietly();
    [$supervisor, $manager] = User::factory()->count(2)->create();
    $supervisor->forceFill(['old_id' => 501])->save();
    $manager->forceFill(['old_id' => 502])->save();
    $sector = Sector::factory()->create();
    $sector->forceFill(['old_id' => 9])->save();
    $tag = Tag::factory()->create();
    $tag->forceFill(['old_id' => 3])->save();
    CustomFieldDefinition::factory()->create([
        'entity_type' => 'registries', 'key' => 'tags', 'type' => 'relation',
        'relation_target' => ['entity_type' => 'tags', 'cardinality' => 'many', 'for_select_resource' => 'tags'],
    ]);

    $activityBefore = Activity::query()->count();

    $report = runRegistriesRealign(true, realignRegistryRecord([
        'company_name' => 'Acme Group Srl', 'supervisor_user_id' => 501, 'manager_user_ids' => [502], 'sector_ids' => [9],
        'tag_ids' => [3], 'is_supplier' => true, 'is_qualified_supplier' => true, 'commercial_referent_id' => 999,
    ]));

    $fresh = $registry->fresh(['managers']);

    expect($fresh->name)->toBe('Acme Group Srl')
        ->and($fresh->personalData->company_name)->toBe('Acme Group Srl')
        ->and($fresh->supervisor_id)->toBe($supervisor->id)
        ->and($fresh->managers->pluck('pivot.position', 'id')->all())->toBe([$manager->id => 1])
        ->and($fresh->sectors()->pluck('sectors.id')->all())->toBe([$sector->id])
        ->and($fresh->custom_fields)->toBe(['tags' => [$tag->id]])
        ->and($fresh->commercial_id)->toBe($commercial->id)
        ->and($fresh->is_qualified_supplier)->toBeTrue()
        ->and($fresh->updated_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00')
        ->and($report->counters['registries'][LegacyRealignReport::ALIGNED])->toBe(1)
        ->and(Activity::query()->count())->toBe($activityBefore);
});

it('never empties a contact or the address when the legacy is blank (AC-018)', function () {
    $registry = realignedRegistry();

    $report = runRegistriesRealign(true, realignRegistryRecord(['phone' => null, 'email' => '', 'street' => null, 'postal_code' => null]));

    $address = $registry->personalData()->first()->addresses()->first();

    expect(registryPrimaryContact($registry, ContactTypeEnum::Phone))->toBe('+39 06 1234567')
        ->and(registryPrimaryContact($registry, ContactTypeEnum::Email))->toBe('info@acme.test')
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->postal_code)->toBe('00100')
        ->and($report->counters['registries'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('updates the primary contacts and adds a missing one, keeping normalized_value', function () {
    $registry = realignedRegistry();

    $report = runRegistriesRealign(true, realignRegistryRecord(['email' => 'new@acme.test', 'pec' => 'acme@pec.test']));

    $card = $registry->personalData()->first();

    expect($card->primaryContact(ContactTypeEnum::Email)->value)->toBe('new@acme.test')
        ->and($card->primaryContact(ContactTypeEnum::Email)->normalized_value)->toBe('new@acme.test')
        ->and($card->primaryContact(ContactTypeEnum::Pec)->value)->toBe('acme@pec.test')
        ->and(array_column($report->changes, 4))->toContain('contact.email', 'contact.pec');
});

it('treats a change to a contact of the card as a change on qnet-2', function () {
    $registry = realignedRegistry();
    $contact = $registry->personalData()->first()->contacts()->first();
    activity()->performedOn($contact)->event('updated')->log('updated');

    $report = runRegistriesRealign(true, realignRegistryRecord(['company_name' => 'Other Srl']));

    expect($registry->fresh()->name)->toBe('Acme Srl')
        ->and($report->counters['registries'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1);
});

it('does not count the import entry on the card as a change', function () {
    $registry = realignedRegistry();
    /** @var PersonalData $card */
    $card = $registry->personalData()->first();
    activity()->performedOn($card)->event('created')->log('created');

    runRegistriesRealign(true, realignRegistryRecord(['company_name' => 'Other Srl']));

    expect($registry->fresh()->name)->toBe('Other Srl');
});

it('fails the row when a manager is not migrated (AC-019)', function () {
    $registry = realignedRegistry();

    $report = runRegistriesRealign(true, realignRegistryRecord(['company_name' => 'Other Srl', 'manager_user_ids' => [404]]));

    expect($registry->fresh()->name)->toBe('Acme Srl')
        ->and($report->counters['registries'][LegacyRealignReport::FAILED])->toBe(1);
});
