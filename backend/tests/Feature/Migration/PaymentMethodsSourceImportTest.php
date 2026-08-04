<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Models\MigrationRun;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor/runMigrationJobFor) are defined once, guarded by
// function_exists, across the Migration feature suite (see CompaniesSourceImportTest).
if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
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

/**
 * Fake the external payment-methods listing with the given records.
 *
 * @param  array<int, array<string, mixed>>  $items
 */
function fakePaymentMethodsResponse(array $items): void
{
    seedMigrationsConfig();

    Http::fake([
        fakeMigrationsBaseUrl().'/payment-methods*' => Http::response([
            'items' => $items,
            'pagination' => ['total' => count($items)],
        ]),
    ]);
}

function runPaymentMethodsImport(): MigrationRun
{
    $run = MigrationRun::factory()->create([
        'user_id' => migrationsSuperAdminActor()->id,
        'source' => 'payment-methods',
    ]);

    runMigrationJobFor($run);

    return $run;
}

// ---------------------------------------------------------------------------
// Create + old_id (standalone lookup)
// ---------------------------------------------------------------------------

it('creates payment methods with their old_id and server-managed sort order', function () {
    fakePaymentMethodsResponse([
        ['id' => 3, 'name' => 'Bonifico bancario', 'code' => 'bank_transfer', 'description' => 'Bonifico ordinario.', 'payment_instructions' => 'IBAN in fattura.', 'payment_days' => 30, 'is_active' => true],
        ['id' => 4, 'name' => 'Contanti', 'code' => 'cash', 'payment_days' => 0, 'is_active' => false],
    ]);

    $run = runPaymentMethodsImport();

    $transfer = PaymentMethod::query()->where('old_id', 3)->first();
    $cash = PaymentMethod::query()->where('old_id', 4)->first();

    expect($transfer->name)->toBe('Bonifico bancario')
        ->and($transfer->code)->toBe('bank_transfer')
        ->and($transfer->description)->toBe('Bonifico ordinario.')
        ->and($transfer->payment_instructions)->toBe('IBAN in fattura.')
        ->and($transfer->payment_days)->toBe(30)
        ->and($transfer->is_active)->toBeTrue()
        ->and($transfer->sort_order)->toBeGreaterThan(0)
        ->and($cash->code)->toBe('cash')
        ->and($cash->description)->toBeNull()
        ->and($cash->payment_days)->toBe(0)
        ->and($cash->is_active)->toBeFalse()
        ->and($cash->sort_order)->toBeGreaterThan($transfer->sort_order);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('re-importing the same payment methods is idempotent (skip, no duplicate)', function () {
    fakePaymentMethodsResponse([
        ['id' => 9, 'name' => 'Assegno', 'code' => 'check'],
    ]);

    runPaymentMethodsImport();
    $secondRun = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'check')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

// ---------------------------------------------------------------------------
// Adoption of the already provisioned catalogue (unique code/name)
// ---------------------------------------------------------------------------

it('adopts an existing unclaimed payment method by code instead of duplicating it', function () {
    $seeded = PaymentMethod::factory()->create(['name' => 'Carta di credito', 'code' => 'credit_card']);

    fakePaymentMethodsResponse([
        ['id' => 12, 'name' => 'Carta di credito (legacy)', 'code' => 'credit_card'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'credit_card')->count())->toBe(1)
        ->and($seeded->fresh()->old_id)->toBe(12)
        ->and($seeded->fresh()->name)->toBe('Carta di credito')
        ->and($run->fresh()->created_rows)->toBe(1);
});

it('fails the row when its code is already migrated under another external id', function () {
    PaymentMethod::factory()->create(['name' => 'Contrassegno', 'code' => 'cash_on_delivery', 'old_id' => 77]);

    fakePaymentMethodsResponse([
        ['id' => 78, 'name' => 'Contrassegno legacy', 'code' => 'cash_on_delivery'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->count())->toBe(1)
        ->and($run->fresh()->failed_rows)->toBe(1)
        ->and(collect($run->fresh()->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('fails the row when the name is already taken by another payment method', function () {
    PaymentMethod::factory()->create(['name' => 'Bonifico', 'code' => 'bank_transfer']);

    fakePaymentMethodsResponse([
        ['id' => 21, 'name' => 'Bonifico', 'code' => 'wire'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'wire')->exists())->toBeFalse()
        ->and($run->fresh()->failed_rows)->toBe(1);
});

// ---------------------------------------------------------------------------
// Field mapping
// ---------------------------------------------------------------------------

it('derives a valid code from the name when the external record carries none', function () {
    fakePaymentMethodsResponse([
        ['id' => 31, 'name' => 'Pagamento rateale 12 mesi'],
        ['id' => 32, 'name' => '30 giorni fine mese'],
    ]);

    runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('old_id', 31)->value('code'))->toBe('pagamento_rateale_12_mesi')
        ->and(PaymentMethod::query()->where('old_id', 32)->value('code'))->toBe('pm_30_giorni_fine_mese');
});

it('defaults is_active to true and keeps blank text fields null', function () {
    fakePaymentMethodsResponse([
        ['id' => 41, 'name' => 'Ricevuta bancaria', 'code' => 'riba', 'description' => '   ', 'payment_instructions' => ''],
    ]);

    runPaymentMethodsImport();

    $method = PaymentMethod::query()->where('old_id', 41)->first();

    expect($method->is_active)->toBeTrue()
        ->and($method->description)->toBeNull()
        ->and($method->payment_instructions)->toBeNull()
        ->and($method->payment_days)->toBeNull();
});

it('warns and leaves payment_days empty when the external value is out of range', function () {
    fakePaymentMethodsResponse([
        ['id' => 51, 'name' => 'Dilazione anomala', 'code' => 'odd_terms', 'payment_days' => 99999],
    ]);

    $run = runPaymentMethodsImport();

    $fresh = $run->fresh();

    expect(PaymentMethod::query()->where('old_id', 51)->value('payment_days'))->toBeNull()
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(0)
        ->and(collect($fresh->report)->firstWhere('level', 'warning'))->not->toBeNull();
});

it('isolates a failed row (missing name) without blocking the valid one', function () {
    fakePaymentMethodsResponse([
        ['id' => 61, 'name' => '', 'code' => 'blank'],
        ['id' => 62, 'name' => 'Paypal', 'code' => 'paypal'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'paypal')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
