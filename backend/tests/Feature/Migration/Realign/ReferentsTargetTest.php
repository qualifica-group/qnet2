<?php

use App\Enums\ContactTypeEnum;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\Targets\ReferentsTarget;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => seedMigrationsConfig());

function realignReferentRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 10, 'referent_type_id' => null, 'contact_scope' => 'internal', 'first_name' => 'Ada', 'last_name' => 'Rossi',
        'tax_code' => null, 'vat_number' => null, 'birth_date' => null, 'gender' => null, 'notes' => null,
        'email' => 'ada@acme.test', 'pec' => null, 'phone' => '+39 06 1234567', 'mobile' => null, 'fax' => null,
        'street' => 'Via Roma 1', 'city' => null, 'postal_code' => '00100',
        'created_at' => '2019-03-04 10:11:12', 'updated_at' => '2026-11-01 10:00:00',
    ], $overrides);
}

function realignedReferent(): Referent
{
    $referent = Referent::factory()->create(['name' => 'Ada Rossi', 'contact_scope' => 'internal', 'referent_type_id' => null, 'notes' => null]);
    $referent->forceFill(['old_id' => 10])->saveQuietly();
    $card = $referent->personalData()->create(['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Rossi']);
    $card->contacts()->create(['type' => 'email', 'value' => 'ada@acme.test', 'is_primary' => true]);
    $card->contacts()->create(['type' => 'phone', 'value' => '+39 06 1234567', 'is_primary' => true]);
    $card->addresses()->create(['line1' => 'Via Roma 1', 'postal_code' => '00100', 'is_primary' => true]);
    Activity::query()->delete();

    return $referent;
}

function runReferentsRealign(bool $apply, array $record): LegacyRealignReport
{
    fakeLegacyRealign('referents', [10], [], [$record]);
    $report = new LegacyRealignReport;
    app(LegacyRealignRunner::class)->run(app(ReferentsTarget::class), '2026-10-23', $apply, $report);

    return $report;
}

it('aligns the card, the derived name, type and notes', function () {
    $referent = realignedReferent();
    $type = ReferentType::factory()->create();
    $type->forceFill(['old_id' => 5])->save();

    $activityBefore = Activity::query()->count();

    $report = runReferentsRealign(true, realignReferentRecord([
        'last_name' => 'Bianchi', 'referent_type_id' => 5, 'notes' => 'Nota', 'gender' => 'F', 'birth_date' => '1980-02-03',
    ]));

    $fresh = $referent->fresh();

    expect($fresh->name)->toBe('Ada Bianchi')
        ->and($fresh->personalData->last_name)->toBe('Bianchi')
        ->and($fresh->referent_type_id)->toBe($type->id)
        ->and($fresh->notes)->toBe('Nota')
        ->and($fresh->updated_at->format('Y-m-d H:i:s'))->toBe('2026-11-01 10:00:00')
        ->and(array_column($report->changes, 4))->toContain('name', 'last_name', 'notes', 'type')
        ->and(Activity::query()->count())->toBe($activityBefore);
});

it('never empties a phone the legacy leaves blank (AC-018)', function () {
    $referent = realignedReferent();

    $report = runReferentsRealign(true, realignReferentRecord(['phone' => null]));

    expect($referent->personalData()->first()->primaryContact(ContactTypeEnum::Phone)->value)->toBe('+39 06 1234567')
        ->and($report->counters['referents'][LegacyRealignReport::UNCHANGED])->toBe(1);
});

it('uses the mobile as the primary phone, as the import does', function () {
    $referent = realignedReferent();

    runReferentsRealign(true, realignReferentRecord(['mobile' => '+39 333 1234567']));

    expect($referent->personalData()->first()->primaryContact(ContactTypeEnum::Phone)->value)->toBe('+39 333 1234567');
});

it('does not read the entries of the import as a change, one with an author yes', function () {
    $referent = realignedReferent();
    activity()->performedOn($referent)->event('created')->log('created');
    activity()->performedOn($referent)->event('updated')->log('name derived at import');

    runReferentsRealign(true, realignReferentRecord(['notes' => 'First']));
    expect($referent->fresh()->notes)->toBe('First');

    activity()->performedOn($referent)->causedBy(User::factory()->create())->event('updated')->log('updated');
    $report = runReferentsRealign(true, realignReferentRecord(['notes' => 'Second']));

    expect($referent->fresh()->notes)->toBe('First')
        ->and($report->counters['referents'][LegacyRealignReport::SKIPPED_TOUCHED])->toBe(1);
});

it('keeps an unknown scope and type', function () {
    $referent = realignedReferent();

    runReferentsRealign(true, realignReferentRecord(['contact_scope' => 'nope', 'referent_type_id' => 777, 'notes' => 'Changed']));

    $fresh = $referent->fresh();

    expect($fresh->contact_scope->value)->toBe('internal')
        ->and($fresh->referent_type_id)->toBeNull()
        ->and($fresh->notes)->toBe('Changed');
});

it('fails a referent the legacy sends without a surname', function () {
    $referent = realignedReferent();

    $report = runReferentsRealign(true, realignReferentRecord(['last_name' => '', 'notes' => 'Changed']));

    expect($referent->fresh()->notes)->toBeNull()
        ->and($report->counters['referents'][LegacyRealignReport::FAILED])->toBe(1);
});
