<?php

use App\Enums\FinancialAccountType;
use App\Enums\MigrationStatus;
use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// A valid Italian IBAN (checksum included) for the fixtures.
const LEGACY_ACCOUNT_IBAN = 'IT60X0542811101000000123456';

beforeEach(function () {
    seedMigrationsConfig();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyFinancialAccountRecord(array $overrides = []): array
{
    return array_merge([
        'id' => 13,
        'type' => 'bank_account',
        'name' => 'Banca Generali spa - Qualifica Group srl',
        'company_id' => null,
        'iban' => LEGACY_ACCOUNT_IBAN,
        'account_number' => '0000123456',
        'address_line' => null,
        'postal_code' => null,
        'notes' => null,
        'warnings' => [],
        'created_at' => '2023-10-02 17:14:41',
        'updated_at' => '2023-10-03 09:00:00',
    ], $overrides);
}

/**
 * @param  array<int, array<string, mixed>>  $records
 */
function runLegacyFinancialAccountsImport(array $records, ?User $actor = null): MigrationRun
{
    Http::fake([
        fakeMigrationsBaseUrl().'/financial-accounts*' => Http::response([
            'items' => $records,
            'pagination' => ['total' => count($records)],
        ]),
    ]);

    $run = MigrationRun::factory()->create(['user_id' => ($actor ?? migrationsSuperAdminActor())->id, 'source' => 'financial-accounts']);
    runMigrationJobFor($run);

    return $run->fresh();
}

it('AC-001 imports a bank account with its company and legacy timestamps, without activity log', function () {
    $company = Company::factory()->create();
    $company->forceFill(['old_id' => 17])->save();
    $actor = migrationsSuperAdminActor();
    $activityBefore = DB::table('activity_log')->count();

    $run = runLegacyFinancialAccountsImport([legacyFinancialAccountRecord([
        'company_id' => 17,
        'address_line' => 'Via Roma 1',
        'postal_code' => '81030',
        'notes' => 'Main account',
    ])], $actor);

    $account = FinancialAccount::query()->where('old_id', 13)->sole();

    expect($run->status)->toBe(MigrationStatus::Completed)
        ->and($run->created_rows)->toBe(1)
        ->and($run->report)->toBeNull()
        ->and($account->type)->toBe(FinancialAccountType::BankAccount)
        ->and($account->name)->toBe('Banca Generali spa - Qualifica Group srl')
        ->and($account->company_id)->toBe($company->id)
        ->and($account->iban)->toBe(LEGACY_ACCOUNT_IBAN)
        ->and($account->account_number)->toBe('0000123456')
        ->and($account->address_line)->toBe('Via Roma 1')
        ->and($account->postal_code)->toBe('81030')
        ->and($account->notes)->toBe('Main account')
        ->and($account->created_at->toDateTimeString())->toBe('2023-10-02 17:14:41')
        ->and($account->updated_at->toDateTimeString())->toBe('2023-10-03 09:00:00')
        ->and(DB::table('activity_log')->count())->toBe($activityBefore);
});

it('AC-002 imports a cash box without bank fields', function () {
    runLegacyFinancialAccountsImport([legacyFinancialAccountRecord(['id' => 18, 'type' => 'cash', 'name' => 'Cassa Contanti'])]);

    $account = FinancialAccount::query()->where('old_id', 18)->sole();

    expect($account->type)->toBe(FinancialAccountType::Cash)
        ->and($account->iban)->toBeNull()
        ->and($account->account_number)->toBeNull();
});

it('AC-003 skips an account already imported', function () {
    runLegacyFinancialAccountsImport([legacyFinancialAccountRecord()]);

    $run = runLegacyFinancialAccountsImport([legacyFinancialAccountRecord(['name' => 'Changed'])]);

    expect($run->skipped_rows)->toBe(1)
        ->and(FinancialAccount::query()->sole()->name)->toBe('Banca Generali spa - Qualifica Group srl');
});

it('AC-004 fails the row when the type is missing or not valid', function (?string $type) {
    $run = runLegacyFinancialAccountsImport([legacyFinancialAccountRecord(['type' => $type])]);

    expect($run->failed_rows)->toBe(1)
        ->and(FinancialAccount::query()->count())->toBe(0)
        ->and(legacyTaskErrors($run)[0])->toContain('is not valid; the account is not imported');
})->with(['missing' => [null], 'unknown' => ['wallet']]);

it('AC-005 leaves an invalid IBAN and a company not migrated empty with a warning', function () {
    $run = runLegacyFinancialAccountsImport([legacyFinancialAccountRecord(['iban' => 'IT00 XYZ 123', 'company_id' => 99])]);

    $account = FinancialAccount::query()->sole();

    expect($account->iban)->toBeNull()
        ->and($account->company_id)->toBeNull()
        ->and(legacyTaskWarnings($run))->toHaveCount(2)
        ->and(legacyTaskWarnings($run)[0])->toBe("IBAN 'IT00XYZ123' is not valid, left empty.");
});

it('AC-006 adopts an account created by hand with the same IBAN', function () {
    $manual = FinancialAccount::factory()->create(['type' => FinancialAccountType::BankAccount, 'name' => 'Manual', 'iban' => LEGACY_ACCOUNT_IBAN]);

    $run = runLegacyFinancialAccountsImport([legacyFinancialAccountRecord()]);

    expect(FinancialAccount::query()->count())->toBe(1)
        ->and($manual->fresh()->old_id)->toBe(13)
        ->and($manual->fresh()->name)->toBe('Manual')
        ->and(legacyTaskWarnings($run))->toBe(["IBAN already on account #{$manual->id}: that account is linked to the legacy one."]);
});

it('AC-007 registers the source in phase 2 with an optional companies dependency', function () {
    $source = app(MigrationRegistry::class)->resolve('financial-accounts');

    expect(MigrationOrder::phaseOf('financial-accounts'))->toBe(2)
        ->and(array_map(fn ($dependency) => [$dependency->source, $dependency->required], $source->dependencies()))->toBe([['companies', false]]);
});
