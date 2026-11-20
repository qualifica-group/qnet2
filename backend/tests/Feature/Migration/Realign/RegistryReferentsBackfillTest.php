<?php

use App\Models\Referent;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => migrationsSuperAdminActor());

// ---------------------------------------------------------------------------
// migrations:reimport-registry-referents (spec 0398)
// ---------------------------------------------------------------------------

function referentsBackfillReferent(string $firstName, ?string $lastName = null, ?int $legacyId = null, array $contacts = []): Referent
{
    $referent = Referent::factory()->create(['name' => trim("{$firstName} {$lastName}")]);
    $referent->forceFill(['old_id' => $legacyId])->saveQuietly();
    $card = $referent->personalData()->create(['type' => 'individual', 'first_name' => $firstName, 'last_name' => $lastName]);

    foreach ($contacts as $contact) {
        $card->contacts()->create($contact);
    }

    return $referent;
}

/**
 * @param  list<Referent>  $referents  linked in this order
 */
function referentsBackfillRegistry(int $legacyId, array $referents, ?Referent $primary, ?Referent $commercial = null): Registry
{
    $registry = Registry::factory()->create(['name' => "Registry {$legacyId}"]);
    $registry->forceFill(['old_id' => $legacyId, 'commercial_id' => $commercial?->id])->saveQuietly();
    $registry->personalData()->create(['type' => 'company', 'company_name' => "Registry {$legacyId}"]);

    foreach ($referents as $referent) {
        DB::table('referent_registry')->insert(['registry_id' => $registry->id, 'referent_id' => $referent->id]);
    }

    DB::table('registries')->where('id', $registry->id)->update(['primary_referent_id' => $primary?->id]);

    return $registry->fresh();
}

function referentsBackfillRecord(int $legacyId, array $responsibles, array $commercialIds = []): array
{
    return [
        'id' => $legacyId, 'is_private' => false, 'company_name' => "Registry {$legacyId}",
        'responsible_referents' => $responsibles, 'commercial_contact_ids' => $commercialIds,
    ];
}

function runReferentsBackfill(bool $apply, array $items, ?string $output = null): string
{
    fakeLegacyRealign('registries', [], [], $items);
    $output ??= legacyRealignOutputPath();

    test()->artisan('migrations:reimport-registry-referents', array_filter(['--apply' => $apply, '--output' => $output]))
        ->assertSuccessful();

    return $output;
}

/**
 * @return list<int>
 */
function referentsBackfillLinked(Registry $registry): array
{
    return $registry->referents()->pluck('referents.id')->map(fn ($id): int => (int) $id)->all();
}

it('links a new Referente 1 and makes it the principale in place of the commercial (AC-007)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $registry = referentsBackfillRegistry(60, [$commercial], $commercial, $commercial);
    Activity::query()->delete();

    runReferentsBackfill(true, [referentsBackfillRecord(60, [
        ['slot' => 1, 'contact_id' => null, 'first_name' => 'Francesca', 'last_name' => null, 'phone' => '3346459555'],
    ], [17050])]);

    $registry->refresh();
    $created = Referent::query()->where('name', 'Francesca')->firstOrFail();

    expect(referentsBackfillLinked($registry))->toBe([$commercial->id, $created->id])
        ->and($registry->primary_referent_id)->toBe($created->id)
        ->and($registry->commercial_id)->toBe($commercial->id);
});

it('moves a commercial principale to the first other referent, or to none (AC-008)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $other = referentsBackfillReferent('Anna', 'Verdi', 17051);
    $withOther = referentsBackfillRegistry(61, [$commercial, $other], $commercial);
    $onlyCommercial = referentsBackfillRegistry(62, [$commercial], $commercial, $commercial);

    $path = runReferentsBackfill(true, [
        referentsBackfillRecord(61, [], [17050]),
        referentsBackfillRecord(62, []),
    ]);

    expect($withOther->fresh()->primary_referent_id)->toBe($other->id)
        ->and($onlyCommercial->fresh()->primary_referent_id)->toBeNull()
        ->and(referentsBackfillLinked($onlyCommercial))->toBe([$commercial->id])
        ->and(readLegacyRealignReport($path)['Principali cambiati'])->toBe([
            ['legacy_id', 'qnet2_id', 'registry', 'old_primary', 'new_primary', 'reason'],
            [61, $withOther->id, 'Registry 61', 'Biagio Fusco', 'Anna Verdi', 'commerciale'],
            [62, $onlyCommercial->id, 'Registry 62', 'Biagio Fusco', '', 'commerciale'],
        ]);
});

it('keeps a principale changed by hand, linking the referents anyway (AC-009)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $registry = referentsBackfillRegistry(63, [$commercial], $commercial, $commercial);
    activity()->performedOn($registry)->event('updated')
        ->withProperties(['old' => ['primary_referent_id' => null], 'attributes' => ['primary_referent_id' => $commercial->id]])
        ->log('updated');

    runReferentsBackfill(true, [referentsBackfillRecord(63, [
        ['slot' => 1, 'contact_id' => null, 'first_name' => 'Mario', 'last_name' => 'Rossi'],
    ])]);

    $registry->refresh();

    expect($registry->primary_referent_id)->toBe($commercial->id)
        ->and($registry->referents()->count())->toBe(2);
});

it('matches an existing referent, adding only its missing contacts (AC-010)', function () {
    $palladino = referentsBackfillReferent('Francesco', 'Palladino', 19078, [
        ['type' => 'phone', 'value' => '335 8458401', 'label' => 'Manuale', 'is_primary' => true],
    ]);
    $registry = referentsBackfillRegistry(64, [$palladino], $palladino);

    runReferentsBackfill(true, [referentsBackfillRecord(64, [
        ['slot' => 1, 'contact_id' => 19078, 'first_name' => 'Francesco', 'last_name' => 'Palladino',
            'phone' => '3358458401', 'email' => 'tecnico@demoltech.test'],
    ])]);

    $contacts = $palladino->personalData()->firstOrFail()->contacts()->orderBy('id')->get();

    expect(Referent::query()->count())->toBe(1)
        ->and($contacts->map(fn ($c): array => [$c->type->value, $c->value, $c->label, $c->is_primary])->all())->toBe([
            ['phone', '335 8458401', 'Manuale', true],
            ['email', 'tecnico@demoltech.test', 'Email', true],
        ])
        ->and($registry->fresh()->primary_referent_id)->toBe($palladino->id);
});

it('writes nothing in simulation and lists in the report what it would do (AC-011)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $registry = referentsBackfillRegistry(65, [$commercial], $commercial, $commercial);
    Activity::query()->delete();

    $path = runReferentsBackfill(false, [referentsBackfillRecord(65, [
        ['slot' => 1, 'contact_id' => null, 'first_name' => 'FILIPPO', 'last_name' => null, 'phone' => '3357109699'],
    ])]);

    $sheets = readLegacyRealignReport($path);

    expect(Referent::query()->count())->toBe(1)
        ->and(referentsBackfillLinked($registry))->toBe([$commercial->id])
        ->and($registry->fresh()->primary_referent_id)->toBe($commercial->id)
        ->and(Activity::query()->count())->toBe(0)
        ->and($sheets['Referenti collegati'][1])->toBe([65, $registry->id, 'Registry 65', 1, '', 'FILIPPO', true, 1])
        ->and($sheets['Principali cambiati'][1])->toBe([65, $registry->id, 'Registry 65', 'Biagio Fusco', 'FILIPPO', 'referente_1']);
});

it('changes nothing on a second run and logs no activity (AC-012, AC-013)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $registry = referentsBackfillRegistry(66, [$commercial], $commercial, $commercial);
    Activity::query()->delete();
    $record = referentsBackfillRecord(66, [
        ['slot' => 1, 'contact_id' => null, 'first_name' => 'FILIPPO', 'last_name' => null, 'phone' => '3357109699'],
        ['slot' => 2, 'contact_id' => null, 'first_name' => 'Tiziana', 'last_name' => null],
    ]);

    runReferentsBackfill(true, [$record]);
    $afterFirst = [referentsBackfillLinked($registry), $registry->fresh()->primary_referent_id, DB::table('contacts')->count()];
    $path = runReferentsBackfill(true, [$record]);
    $sheets = readLegacyRealignReport($path);

    expect([referentsBackfillLinked($registry), $registry->fresh()->primary_referent_id, DB::table('contacts')->count()])->toBe($afterFirst)
        ->and(Referent::query()->count())->toBe(3)
        ->and(Activity::query()->count())->toBe(0)
        ->and($sheets['Principali cambiati'])->toHaveCount(1)
        ->and(collect($sheets['Referenti collegati'])->skip(1)->pluck(6)->all())->toBe([false, false]);
});

it('changes nothing when the legacy does not send the Referente 1/2 fields (AC-014)', function () {
    $commercial = referentsBackfillReferent('Biagio', 'Fusco', 17050);
    $registry = referentsBackfillRegistry(67, [$commercial], $commercial, $commercial);

    runReferentsBackfill(true, [['id' => 67, 'is_private' => false, 'company_name' => 'Registry 67']]);

    expect($registry->fresh()->primary_referent_id)->toBe($commercial->id);
});

// ---------------------------------------------------------------------------
// Spec 0408: Referente 2 becomes the anagrafica's secondario
// ---------------------------------------------------------------------------

it('makes Referente 2 the secondario, even behind a linked commercial, once (spec 0408 AC-006)', function () {
    $commercial = referentsBackfillReferent('Marco', 'Baldi', 6169);
    $orazio = referentsBackfillReferent('Claudio', 'Orazio', 16106);
    $registry = referentsBackfillRegistry(13036, [$commercial, $orazio], $commercial, $commercial);
    $record = referentsBackfillRecord(13036, [
        ['slot' => 1, 'contact_id' => 16106, 'first_name' => 'Claudio', 'last_name' => 'Orazi', 'email' => 'info@oraziclaudio.it'],
        ['slot' => 2, 'contact_id' => null, 'first_name' => 'PASQUALINA', 'last_name' => 'PAGANO', 'phone' => '3519073758'],
    ], [6169]);

    $path = runReferentsBackfill(true, [$record]);
    $pagano = Referent::query()->where('name', 'PASQUALINA PAGANO')->firstOrFail();
    $registry->refresh();

    expect($registry->primary_referent_id)->toBe($orazio->id)
        ->and($registry->secondary_referent_id)->toBe($pagano->id)
        ->and(readLegacyRealignReport($path)['Secondari impostati'])->toBe([
            ['legacy_id', 'qnet2_id', 'registry', 'old_secondary', 'new_secondary'],
            [13036, $registry->id, 'Registry 13036', '', 'PASQUALINA PAGANO'],
        ]);

    $again = runReferentsBackfill(true, [$record]);

    expect($registry->fresh()->secondary_referent_id)->toBe($pagano->id)
        ->and(readLegacyRealignReport($again)['Secondari impostati'])->toHaveCount(1)
        ->and(Referent::query()->count())->toBe(3);
});

it('keeps a secondario changed by hand (spec 0408 AC-006)', function () {
    $mine = referentsBackfillReferent('Anna', 'Verdi', 17051);
    $first = referentsBackfillReferent('Mario', 'Rossi', 17052);
    $registry = referentsBackfillRegistry(68, [$first, $mine], $first);
    DB::table('registries')->where('id', $registry->id)->update(['secondary_referent_id' => $mine->id]);
    activity()->performedOn($registry)->event('updated')
        ->withProperties(['old' => ['secondary_referent_id' => null], 'attributes' => ['secondary_referent_id' => $mine->id]])
        ->log('updated');

    runReferentsBackfill(true, [referentsBackfillRecord(68, [
        ['slot' => 1, 'contact_id' => 17052, 'first_name' => 'Mario', 'last_name' => 'Rossi'],
        ['slot' => 2, 'contact_id' => null, 'first_name' => 'Tiziana', 'last_name' => null],
    ])]);

    expect($registry->fresh()->secondary_referent_id)->toBe($mine->id)
        ->and($registry->referents()->count())->toBe(3);
});

it('never makes the principale a secondario changed by hand (spec 0408 INV-2)', function () {
    $current = referentsBackfillReferent('Mario', 'Rossi', 17061);
    $mine = referentsBackfillReferent('Anna', 'Verdi', 17062);
    $registry = referentsBackfillRegistry(8582, [$current, $mine], $current);
    DB::table('registries')->where('id', $registry->id)->update(['secondary_referent_id' => $mine->id]);
    activity()->performedOn($registry)->event('updated')
        ->withProperties(['old' => ['secondary_referent_id' => null], 'attributes' => ['secondary_referent_id' => $mine->id]])
        ->log('updated');

    runReferentsBackfill(true, [referentsBackfillRecord(8582, [
        ['slot' => 1, 'contact_id' => 17062, 'first_name' => 'Anna', 'last_name' => 'Verdi'],
    ])]);
    $registry->refresh();

    expect($registry->primary_referent_id)->toBe($current->id)
        ->and($registry->secondary_referent_id)->toBe($mine->id);
});

it('clears a secondario set by the import when Referente 1 moves onto it (spec 0408 INV-2)', function () {
    $current = referentsBackfillReferent('Mario', 'Rossi', 17071);
    $swapped = referentsBackfillReferent('Anna', 'Verdi', 17072);
    $registry = referentsBackfillRegistry(8583, [$current, $swapped], $current);
    DB::table('registries')->where('id', $registry->id)->update(['secondary_referent_id' => $swapped->id]);

    runReferentsBackfill(true, [referentsBackfillRecord(8583, [
        ['slot' => 1, 'contact_id' => 17072, 'first_name' => 'Anna', 'last_name' => 'Verdi'],
    ])]);
    $registry->refresh();

    expect($registry->primary_referent_id)->toBe($swapped->id)
        ->and($registry->secondary_referent_id)->toBeNull();
});
