<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\Country;
use App\Models\MigrationRun;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers are defined once, guarded by function_exists, across the
// Migration feature suite (see CompaniesSourceImportTest).
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationService::class));
    }
}

// ---------------------------------------------------------------------------
// ReferentsSource — full profile (card, address, contacts) + type remap
// ---------------------------------------------------------------------------

it('creates a referent with its card, primary address, contacts and derived name', function () {
    seedMigrationsConfig();
    $country = Country::factory()->create(['name' => 'Italy']);
    State::factory()->for($country)->create(['name' => 'Lazio']);

    // The referent's type is a phase-1 anchor, referenced by its EXTERNAL id.
    $type = ReferentType::factory()->create(['name' => 'Supplier']);
    $type->old_id = 7;
    $type->save();

    Http::fake([
        fakeMigrationsBaseUrl().'/referents*' => Http::response([
            'items' => [[
                'id' => 10,
                'referent_type_id' => 7,
                'contact_scope' => 'external',
                'first_name' => 'Mario',
                'last_name' => 'Rossi',
                'tax_code' => 'RSSMRA80A01H501U',
                'notes' => 'Key account',
                'country' => 'Italy',
                'region' => 'Lazio',
                'city' => 'Rome',
                'street' => 'Via Roma 1',
                'postal_code' => '00100',
                'email' => 'mario.rossi@example.com',
                'pec' => 'mario.rossi@pec.example.com',
                'phone' => '+39 06 1234567',
                'mobile' => '+39 333 1234567',
                'fax' => '+39 06 7654321',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']);

    runMigrationJobFor($run);

    $referent = Referent::query()->where('old_id', 10)->with('personalData.contacts', 'personalData.addresses')->first();

    expect($referent)->not->toBeNull()
        ->and($referent->name)->toBe('Mario Rossi')
        ->and($referent->contact_scope->value)->toBe('external')
        ->and($referent->referent_type_id)->toBe($type->id)
        ->and($referent->notes)->toBe('Key account')
        ->and($referent->personalData?->first_name)->toBe('Mario')
        ->and($referent->personalData?->tax_code)->toBe('RSSMRA80A01H501U');

    $address = $referent->personalData->addresses->first();
    expect($address)->not->toBeNull()
        ->and($address->is_primary)->toBeTrue()
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->country_id)->toBe($country->id);

    // Every migrated contact is flagged primary; the external `mobile` is a
    // second phone written AFTER the landline, so the one-primary-per-type
    // invariant keeps the former mobile as the primary phone (spec 0139 D-7).
    $contacts = $referent->personalData->contacts;
    expect($contacts)->toHaveCount(5)
        ->and($contacts->where('type', 'email'))->toHaveCount(1)
        ->and($contacts->where('type', 'pec'))->toHaveCount(1)
        ->and($contacts->where('type', 'phone'))->toHaveCount(2)
        ->and($contacts->where('type', 'fax'))->toHaveCount(1)
        ->and($contacts->where('type', 'phone')->firstWhere('is_primary', true)?->value)->toBe('+39 333 1234567')
        ->and($contacts->where('is_primary', true))->toHaveCount(4);

    expect($run->fresh()->created_rows)->toBe(1);
});

it('warns and leaves the type null when referent_type_id does not resolve', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/referents*' => Http::response([
            'items' => [[
                'id' => 11,
                'referent_type_id' => 999, // no migrated type has this old_id
                'contact_scope' => 'internal',
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']);

    runMigrationJobFor($run);

    $referent = Referent::query()->where('old_id', 11)->first();
    expect($referent)->not->toBeNull()
        ->and($referent->referent_type_id)->toBeNull();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'warning')['message'])->toContain('referent_type_id');
});

it('defaults an unknown contact_scope to the enum default with a warning', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/referents*' => Http::response([
            'items' => [[
                'id' => 12,
                'contact_scope' => 'bogus',
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']);

    runMigrationJobFor($run);

    $referent = Referent::query()->where('old_id', 12)->first();
    expect($referent->contact_scope->value)->toBe('internal')
        ->and(collect($run->fresh()->report)->firstWhere('level', 'warning')['message'])->toContain('contact_scope');
});

it('re-importing the same referents is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/referents*' => Http::response([
            'items' => [[
                'id' => 13,
                'contact_scope' => 'internal',
                'first_name' => 'Alan',
                'last_name' => 'Turing',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']);
    runMigrationJobFor($secondRun);

    expect(Referent::query()->where('old_id', 13)->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed referent row (missing last_name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/referents*' => Http::response([
            'items' => [
                ['id' => 14, 'contact_scope' => 'internal', 'first_name' => 'NoLast', 'last_name' => ''],
                ['id' => 15, 'contact_scope' => 'internal', 'first_name' => 'Katherine', 'last_name' => 'Johnson'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'referents']);

    runMigrationJobFor($run);

    expect(Referent::query()->where('old_id', 15)->exists())->toBeTrue()
        ->and(Referent::query()->where('old_id', 14)->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
