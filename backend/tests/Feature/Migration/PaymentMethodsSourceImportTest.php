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
        ['id' => 3, 'name' => 'Bonifico bancario', 'code' => 'MP05', 'description' => 'Bonifico ordinario.', 'payment_instructions' => 'IBAN in fattura.', 'payment_days' => 30, 'is_active' => true],
        ['id' => 4, 'name' => 'Contanti', 'code' => 'MP01', 'payment_days' => 0, 'is_active' => false],
    ]);

    $run = runPaymentMethodsImport();

    $transfer = PaymentMethod::query()->where('old_id', 3)->first();
    $cash = PaymentMethod::query()->where('old_id', 4)->first();

    expect($transfer->name)->toBe('Bonifico bancario')
        // qnet's unique `code` is derived from the name; the external one is
        // the fiscal classification and lands on `payment_method_code`.
        ->and($transfer->code)->toBe('bonifico_bancario')
        ->and($transfer->payment_method_code)->toBe('MP05')
        ->and($transfer->description)->toBe('Bonifico ordinario.')
        ->and($transfer->payment_instructions)->toBe('IBAN in fattura.')
        ->and($transfer->payment_days)->toBe(30)
        ->and($transfer->is_active)->toBeTrue()
        ->and($transfer->sort_order)->toBeGreaterThan(0)
        ->and($cash->code)->toBe('contanti')
        ->and($cash->payment_method_code)->toBe('MP01')
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
        ['id' => 9, 'name' => 'Assegno', 'code' => 'MP02'],
    ]);

    runPaymentMethodsImport();
    $secondRun = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'assegno')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

// ---------------------------------------------------------------------------
// The fiscal code is a classification, not an identity
// ---------------------------------------------------------------------------

it('imports every method sharing the same fiscal code, distinguished by its own derived code', function () {
    fakePaymentMethodsResponse([
        ['id' => 60, 'name' => 'ADDEBITO CARTA DI CREDITO', 'code' => 'MP01'],
        ['id' => 53, 'name' => 'ADDEBITO F24', 'code' => 'MP01'],
        ['id' => 22, 'name' => 'AVVENUTO', 'code' => 'MP01'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('payment_method_code', 'MP01')->count())->toBe(3)
        ->and(PaymentMethod::query()->orderBy('old_id')->pluck('code')->all())
        ->toBe(['avvenuto', 'addebito_f24', 'addebito_carta_di_credito'])
        ->and($run->fresh()->created_rows)->toBe(3)
        ->and($run->fresh()->failed_rows)->toBe(0);
});

it('gives two homonymous legacy methods distinct codes, keeping both rows', function () {
    fakePaymentMethodsResponse([
        ['id' => 81, 'name' => 'RI.BA. 60/90 GG F.M.', 'code' => 'MP12'],
        ['id' => 82, 'name' => 'RI.BA. 60/90 GG F.M.', 'code' => 'MP12'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('name', 'RI.BA. 60/90 GG F.M.')->count())->toBe(2)
        ->and(PaymentMethod::query()->where('old_id', 81)->value('code'))->toBe('ri_ba_60_90_gg_f_m')
        ->and(PaymentMethod::query()->where('old_id', 82)->value('code'))->toBe('ri_ba_60_90_gg_f_m_82')
        ->and($run->fresh()->failed_rows)->toBe(0);
});

// ---------------------------------------------------------------------------
// Adoption of the already provisioned catalogue (unique code)
// ---------------------------------------------------------------------------

it('adopts an existing unclaimed payment method by derived code instead of duplicating it', function () {
    $seeded = PaymentMethod::factory()->create(['name' => 'Carta di credito', 'code' => 'carta_di_credito']);

    fakePaymentMethodsResponse([
        ['id' => 12, 'name' => 'Carta di credito', 'code' => 'MP08'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'carta_di_credito')->count())->toBe(1)
        ->and($seeded->fresh()->old_id)->toBe(12)
        ->and($run->fresh()->created_rows)->toBe(1);
});

// ---------------------------------------------------------------------------
// Field mapping
// ---------------------------------------------------------------------------

it('derives a snake_case code from the name, prefixing one that does not start with a letter', function () {
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
        ['id' => 41, 'name' => 'Ricevuta bancaria', 'code' => '   ', 'description' => '   ', 'payment_instructions' => ''],
    ]);

    runPaymentMethodsImport();

    $method = PaymentMethod::query()->where('old_id', 41)->first();

    expect($method->is_active)->toBeTrue()
        ->and($method->payment_method_code)->toBeNull()
        ->and($method->description)->toBeNull()
        ->and($method->payment_instructions)->toBeNull()
        ->and($method->payment_days)->toBeNull();
});

it('warns and leaves payment_days empty when the external value is out of range', function () {
    fakePaymentMethodsResponse([
        ['id' => 51, 'name' => 'Dilazione anomala', 'code' => 'MP05', 'payment_days' => 99999],
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
        ['id' => 61, 'name' => '', 'code' => 'MP01'],
        ['id' => 62, 'name' => 'Paypal', 'code' => 'MP08'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'paypal')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
