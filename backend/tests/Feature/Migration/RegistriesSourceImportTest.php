<?php

use App\Models\Country;
use App\Models\MigrationRun;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// RegistriesSource (spec 0189) — legacy `companies` -> anagrafiche
// ---------------------------------------------------------------------------

/**
 * @param  array<int, array<string, mixed>>  $items
 */
function fakeLegacyRegistries(array $items): void
{
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/registries*' => Http::response(['items' => $items, 'pagination' => ['total' => count($items)]]),
    ]);
}

function runRegistriesMigration(?User $actor = null): MigrationRun
{
    $actor ??= migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'registries']);

    runMigrationJobFor($run);

    return $run->fresh();
}

/**
 * @return array<int, string>
 */
function registriesRunWarnings(MigrationRun $run): array
{
    return collect($run->report)->where('level', 'warning')->pluck('message')->all();
}

it('imports a company with every field, remapped relations and no notification or activity log', function () {
    Notification::fake();
    Country::factory()->create(['name' => 'Italy']);
    $source = Source::factory()->create();
    $source->forceFill(['old_id' => 3])->save();
    $sector = Sector::factory()->create(['name' => 'EA 28']);
    $sector->forceFill(['old_id' => 9])->save();
    [$supervisor, $managerOne, $managerTwo] = User::factory()->count(3)->create();
    $supervisor->forceFill(['old_id' => 501])->save();
    $managerOne->forceFill(['old_id' => 502])->save();
    $managerTwo->forceFill(['old_id' => 503])->save();
    [$commercial, $reporter, $contact] = Referent::factory()->count(3)->create();
    $commercial->forceFill(['old_id' => 701])->save();
    $reporter->forceFill(['old_id' => 702])->save();
    $contact->forceFill(['old_id' => 703])->save();
    $actor = migrationsSuperAdminActor();
    $activityRows = DB::table('activity_log')->count();

    fakeLegacyRegistries([[
        'id' => 40, 'is_private' => false, 'company_name' => 'Acme Srl', 'first_name' => null, 'last_name' => null,
        'tax_code' => ' 01234567890 ', 'vat_number' => 'IT01234567890', 'sdi_code' => 'ABC1234',
        'vat_group' => 'GRP-1', 'is_supplier' => true, 'is_qualified_supplier' => true,
        'agreement_status' => 'agreed', 'agreement_notes' => 'Signed 2020', 'size_class' => 'small', 'employee_count' => 42,
        'source_id' => 3, 'source_label' => 'Fiera', 'sectors' => ['EA 28'],
        'supervisor_user_id' => 501, 'manager_user_ids' => [502, 503],
        'commercial_referent_id' => 701, 'reporter_referent_id' => 702, 'referent_ids' => [703],
        'email' => 'info@acme.test', 'pec' => 'acme@pec.test', 'phone' => '+39 06 1234567', 'phone2' => '+39 06 7654321',
        'fax' => '+39 06 1111111', 'website' => 'www.acme.test',
        'country' => 'Italy', 'city' => 'Roma', 'street' => 'Via Roma 1', 'postal_code' => '00100',
        'addresses' => [['site_type' => 'delivery', 'country' => 'Italy', 'city' => 'Milano', 'street' => 'Via Milano 2', 'postal_code' => '20100']],
        'created_at' => '2019-03-04 10:11:12', 'updated_at' => '2021-05-06 07:08:09',
    ]]);

    $run = runRegistriesMigration($actor);

    $registry = Registry::query()->where('old_id', 40)->with('personalData.contacts', 'personalData.addresses', 'managers')->firstOrFail();
    $card = $registry->personalData;

    expect($run->created_rows)->toBe(1)
        ->and($registry->name)->toBe('Acme Srl')
        ->and($card->type->value)->toBe('company')
        ->and($card->vat_number)->toBe('01234567890')
        ->and($card->tax_code)->toBe('01234567890')
        ->and($card->sdi_code)->toBe('ABC1234')
        ->and($registry->vat_group)->toBe('GRP-1')
        ->and($registry->is_supplier)->toBeTrue()
        ->and($registry->is_qualified_supplier)->toBeTrue()
        ->and($registry->agreement_status->value)->toBe('agreed')
        ->and($registry->size_class->value)->toBe('small')
        ->and($registry->employee_count)->toBe(42)
        ->and($registry->source_id)->toBe($source->id)
        ->and($registry->supervisor_id)->toBe($supervisor->id)
        ->and($registry->commercial_id)->toBe($commercial->id)
        ->and($registry->reporter_id)->toBe($reporter->id)
        ->and($registry->referents()->pluck('referents.id')->all())->toBe([$contact->id])
        ->and($registry->sectors()->pluck('sectors.id')->all())->toBe([$sector->id])
        ->and($registry->managers->pluck('pivot.position', 'id')->all())->toBe([$managerOne->id => 1, $managerTwo->id => 2])
        ->and($registry->created_at->format('Y-m-d H:i:s'))->toBe('2019-03-04 10:11:12')
        ->and($registry->updated_at->format('Y-m-d H:i:s'))->toBe('2021-05-06 07:08:09');

    $contacts = $card->contacts;
    expect($contacts)->toHaveCount(6)
        ->and($contacts->firstWhere('type', 'website')->value)->toBe('https://www.acme.test')
        ->and($contacts->where('type', 'phone')->firstWhere('is_primary', true)->value)->toBe('+39 06 1234567');

    $addresses = $card->addresses;
    expect($addresses)->toHaveCount(2)
        ->and($addresses->firstWhere('is_primary', true)->site_type->value)->toBe('legal_seat')
        ->and($addresses->firstWhere('is_primary', true)->line1)->toBe('Via Roma 1')
        ->and($addresses->firstWhere('is_primary', false)->site_type->value)->toBe('delivery');

    Notification::assertNothingSent();
    expect(DB::table('activity_log')->count())->toBe($activityRows);
});

it('imports a private person as an individual card', function () {
    fakeLegacyRegistries([[
        'id' => 41, 'is_private' => true, 'company_name' => 'ignored', 'first_name' => 'Mario', 'last_name' => 'Rossi',
        'tax_code' => 'rssmra80a01h501u', 'vat_number' => null,
    ]]);

    runRegistriesMigration();

    $registry = Registry::query()->where('old_id', 41)->with('personalData')->firstOrFail();

    expect($registry->name)->toBe('Mario Rossi')
        ->and($registry->personalData->type->value)->toBe('individual')
        ->and($registry->personalData->company_name)->toBeNull()
        ->and($registry->personalData->tax_code)->toBe('RSSMRA80A01H501U');
});

it('swaps a first name and surname the legacy inverted, guided by the tax code', function () {
    fakeLegacyRegistries([[
        'id' => 47, 'is_private' => true, 'first_name' => 'Franzese', 'last_name' => 'Filomena',
        'tax_code' => 'FRNFMN70A41F839X',
    ]]);

    $run = runRegistriesMigration();
    $card = Registry::query()->where('old_id', 47)->with('personalData')->firstOrFail()->personalData;

    expect($card->first_name)->toBe('Filomena')
        ->and($card->last_name)->toBe('Franzese')
        ->and($run->report ?? [])->toBe([]);
});

it('discards placeholder VAT numbers with a warning', function (string $placeholder) {
    fakeLegacyRegistries([['id' => 42, 'is_private' => false, 'company_name' => 'Placeholder Spa', 'vat_number' => $placeholder]]);

    $run = runRegistriesMigration();

    expect(Registry::query()->where('old_id', 42)->firstOrFail()->personalData->vat_number)->toBeNull()
        ->and(registriesRunWarnings($run))->toContain("Placeholder vat_number '{$placeholder}' discarded.");
})->with(['0', '1', '.', '00000000000', 'IT00000000000', '12345']);

it('warns on every unresolved reference and still creates the registry', function () {
    fakeLegacyRegistries([[
        'id' => 43, 'is_private' => false, 'company_name' => 'Orphan Srl',
        'source_id' => null, 'source_label' => 'Passaparola', 'sectors' => ['Unknown sector'],
        'supervisor_user_id' => 999, 'manager_user_ids' => [998, 997], 'commercial_referent_id' => 996,
        'reporter_referent_id' => 0, 'referent_ids' => [995], 'agreement_status' => 'maybe', 'size_class' => 'huge',
        'email' => 'not-an-email',
    ]]);

    $run = runRegistriesMigration();
    $registry = Registry::query()->where('old_id', 43)->firstOrFail();

    expect($run->created_rows)->toBe(1)
        ->and($registry->supervisor_id)->toBeNull()
        ->and($registry->commercial_id)->toBeNull()
        ->and($registry->agreement_status)->toBeNull()
        ->and($registry->managers()->count())->toBe(0)
        ->and(registriesRunWarnings($run))->toEqualCanonicalizing([
            "Unresolved source 'Passaparola'.",
            "Unresolved sector 'Unknown sector'.",
            'Unresolved referent_ids (legacy id 995).',
            'Unresolved commercial_referent_id (legacy id 996).',
            "Unknown agreement_status 'maybe', left blank.",
            "Unknown size_class 'huge', left blank.",
            'Invalid email value, skipped.',
            'Unresolved manager_user_ids (legacy id 998).',
            'Unresolved manager_user_ids (legacy id 997).',
            'Unresolved supervisor_user_id (legacy id 999).',
        ]);
});

it('fails a nameless row and keeps importing the others', function () {
    fakeLegacyRegistries([
        ['id' => 44, 'is_private' => true, 'first_name' => ' ', 'last_name' => null],
        ['id' => 45, 'is_private' => false, 'company_name' => 'Valid Srl'],
    ]);

    $run = runRegistriesMigration();

    expect($run->failed_rows)->toBe(1)
        ->and($run->created_rows)->toBe(1)
        ->and(Registry::query()->where('old_id', 44)->exists())->toBeFalse()
        ->and(collect($run->report)->firstWhere('level', 'error')['old_id'])->toBe(44);
});

it('skips already imported registries on a second run', function () {
    fakeLegacyRegistries([['id' => 46, 'is_private' => false, 'company_name' => 'Twice Srl']]);

    runRegistriesMigration();
    $second = runRegistriesMigration();

    expect($second->skipped_rows)->toBe(1)
        ->and($second->created_rows)->toBe(0)
        ->and(Registry::query()->where('old_id', 46)->count())->toBe(1);
});
