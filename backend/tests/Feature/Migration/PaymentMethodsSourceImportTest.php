<?php

use App\Enums\MigrationStatus;
use App\Enums\PaymentCondition;
use App\Enums\VatAllocation;
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
        ['id' => 3, 'name' => 'Bonifico bancario', 'code' => 'bonifico_bancario', 'payment_method_code' => 'MP05', 'description' => 'Bonifico ordinario.', 'payment_instructions' => 'IBAN in fattura.', 'payment_days' => 30, 'is_active' => true],
        ['id' => 4, 'name' => 'Contanti', 'code' => 'contanti', 'payment_method_code' => 'MP01', 'payment_days' => 0, 'is_active' => false],
    ]);

    $run = runPaymentMethodsImport();

    $transfer = PaymentMethod::query()->where('old_id', 3)->first();
    $cash = PaymentMethod::query()->where('old_id', 4)->first();

    expect($transfer->name)->toBe('Bonifico bancario')
        // qnet's unique `code` is the one the legacy sends; the fiscal
        // classification lands on `payment_method_code`.
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
        ['id' => 9, 'name' => 'Assegno', 'code' => 'assegno', 'payment_method_code' => 'MP02'],
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

it('imports every method sharing the same fiscal code, distinguished by its own code', function () {
    fakePaymentMethodsResponse([
        ['id' => 60, 'name' => 'ADDEBITO CARTA DI CREDITO', 'code' => 'addebito_carta_di_credito', 'payment_method_code' => 'MP01'],
        ['id' => 53, 'name' => 'ADDEBITO F24', 'code' => 'addebito_f24', 'payment_method_code' => 'MP01'],
        ['id' => 22, 'name' => 'AVVENUTO', 'code' => 'avvenuto', 'payment_method_code' => 'MP01'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('payment_method_code', 'MP01')->count())->toBe(3)
        ->and(PaymentMethod::query()->orderBy('old_id')->pluck('code')->all())
        ->toBe(['avvenuto', 'addebito_f24', 'addebito_carta_di_credito'])
        ->and($run->fresh()->created_rows)->toBe(3)
        ->and($run->fresh()->failed_rows)->toBe(0);
});

it('REQUIREMENT CHANGED: keeps both rows of two homonymous legacy methods, each under the distinct code the legacy sends', function () {
    fakePaymentMethodsResponse([
        ['id' => 81, 'name' => 'RI.BA. 60/90 GG F.M.', 'code' => 'ri_ba_60_90_gg_f_m', 'payment_method_code' => 'MP12'],
        ['id' => 82, 'name' => 'RI.BA. 60/90 GG F.M.', 'code' => 'ri_ba_60_90_gg_f_m_82', 'payment_method_code' => 'MP12'],
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

it('adopts an existing unclaimed payment method by code instead of duplicating it', function () {
    $seeded = PaymentMethod::factory()->create(['name' => 'Carta di credito', 'code' => 'carta_di_credito']);

    fakePaymentMethodsResponse([
        ['id' => 12, 'name' => 'Carta di credito', 'code' => 'carta_di_credito', 'payment_method_code' => 'MP08'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'carta_di_credito')->count())->toBe(1)
        ->and($seeded->fresh()->old_id)->toBe(12)
        ->and($run->fresh()->created_rows)->toBe(1);
});

// ---------------------------------------------------------------------------
// Field mapping
// ---------------------------------------------------------------------------

it('REQUIREMENT CHANGED: uses the code sent by the legacy as is, and fails a row whose code is missing or malformed', function () {
    fakePaymentMethodsResponse([
        ['id' => 31, 'name' => 'Pagamento rateale 12 mesi', 'code' => 'pagamento_rateale_12_mesi'],
        ['id' => 32, 'name' => '30 giorni fine mese', 'code' => 'pm_30_giorni_fine_mese'],
        ['id' => 33, 'name' => 'Senza codice'],
        ['id' => 34, 'name' => 'Codice sporco', 'code' => 'Codice-Sporco'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('old_id', 31)->value('code'))->toBe('pagamento_rateale_12_mesi')
        ->and(PaymentMethod::query()->where('old_id', 32)->value('code'))->toBe('pm_30_giorni_fine_mese')
        ->and(PaymentMethod::query()->whereIn('old_id', [33, 34])->count())->toBe(0)
        ->and($run->fresh()->created_rows)->toBe(2)
        ->and($run->fresh()->failed_rows)->toBe(2);
});

it('defaults is_active to true and keeps blank text fields null', function () {
    fakePaymentMethodsResponse([
        ['id' => 41, 'name' => 'Ricevuta bancaria', 'code' => 'ricevuta_bancaria', 'payment_method_code' => '   ', 'description' => '   ', 'payment_instructions' => ''],
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
        ['id' => 51, 'name' => 'Dilazione anomala', 'code' => 'dilazione_anomala', 'payment_method_code' => 'MP05', 'payment_days' => 99999],
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
        ['id' => 61, 'name' => '', 'payment_method_code' => 'MP01'],
        ['id' => 62, 'name' => 'Paypal', 'code' => 'paypal', 'payment_method_code' => 'MP08'],
    ]);

    $run = runPaymentMethodsImport();

    expect(PaymentMethod::query()->where('code', 'paypal')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Every legacy field, and the overwrite on re-import (spec 0325)
// ---------------------------------------------------------------------------

/**
 * A legacy record carrying every field of `payments` (spec 0325 data contract).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function legacyPaymentMethodRecord(array $overrides = []): array
{
    return $overrides + [
        'id' => 70,
        'name' => 'RI.BA. 30/60 GG F.M.',
        'code' => 'ri_ba_30_60_gg_f_m',
        'payment_method_code' => 'MP12',
        'payment_condition' => 'TP01',
        'vat_allocation' => 'first',
        'payment_days' => 30,
        'installments_count' => 2,
        'days_between_installments' => 30,
        'end_of_month' => true,
        'end_of_month_extra_days' => 10,
        'excluded_month_1' => 8,
        'excluded_month_2' => 12,
        'description' => null,
        'payment_instructions' => null,
        'is_active' => true,
    ];
}

it('imports every legacy field of the payment method', function () {
    fakePaymentMethodsResponse([legacyPaymentMethodRecord()]);

    $run = runPaymentMethodsImport();

    $method = PaymentMethod::query()->where('old_id', 70)->first();

    expect($method->payment_condition)->toBe(PaymentCondition::Installments)
        ->and($method->vat_allocation)->toBe(VatAllocation::First)
        ->and($method->payment_days)->toBe(30)
        ->and($method->installments_count)->toBe(2)
        ->and($method->days_between_installments)->toBe(30)
        ->and($method->end_of_month)->toBeTrue()
        ->and($method->end_of_month_extra_days)->toBe(10)
        ->and($method->excluded_month_1)->toBe(8)
        ->and($method->excluded_month_2)->toBe(12)
        ->and($run->fresh()->report)->toBeNull();
});

it('REQUIREMENT CHANGED: a re-import overwrites the legacy fields and keeps the fields qnet owns', function () {
    $changed = legacyPaymentMethodRecord([
        'name' => 'RI.BA. 60 GG D.F.',
        'code' => 'ri_ba_60_gg_d_f',
        'payment_condition' => 'TP02',
        'vat_allocation' => 'split',
        'payment_days' => 60,
        'installments_count' => 1,
        'days_between_installments' => 0,
        'end_of_month' => false,
        'end_of_month_extra_days' => null,
        'excluded_month_1' => null,
        'excluded_month_2' => null,
    ]);
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/payment-methods*' => Http::sequence()
            ->push(['items' => [legacyPaymentMethodRecord()], 'pagination' => ['total' => 1]])
            ->push(['items' => [$changed], 'pagination' => ['total' => 1]]),
    ]);
    runPaymentMethodsImport();

    $method = PaymentMethod::query()->where('old_id', 70)->first();
    $method->update(['description' => 'Nota interna', 'payment_instructions' => 'IBAN', 'is_active' => false, 'installments_count' => 5]);
    $sortOrder = $method->sort_order;

    $secondRun = runPaymentMethodsImport();

    $method->refresh();

    expect(PaymentMethod::query()->count())->toBe(1)
        ->and($method->name)->toBe('RI.BA. 60 GG D.F.')
        ->and($method->payment_condition)->toBe(PaymentCondition::Full)
        ->and($method->vat_allocation)->toBe(VatAllocation::Split)
        ->and($method->payment_days)->toBe(60)
        ->and($method->installments_count)->toBe(1)
        ->and($method->end_of_month)->toBeFalse()
        ->and($method->end_of_month_extra_days)->toBeNull()
        ->and($method->excluded_month_1)->toBeNull()
        ->and($method->excluded_month_2)->toBeNull()
        ->and($method->code)->toBe('ri_ba_30_60_gg_f_m')
        ->and($method->sort_order)->toBe($sortOrder)
        ->and($method->is_active)->toBeFalse()
        ->and($method->description)->toBe('Nota interna')
        ->and($method->payment_instructions)->toBe('IBAN')
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->failed_rows)->toBe(0);
});

it('adopts a seeded method and overwrites it with the legacy fields', function () {
    $seeded = PaymentMethod::factory()->create(['name' => 'Ri.Ba.', 'code' => 'ri_ba_30_60_gg_f_m', 'installments_count' => 1]);

    fakePaymentMethodsResponse([legacyPaymentMethodRecord()]);
    runPaymentMethodsImport();

    $seeded->refresh();

    expect($seeded->old_id)->toBe(70)
        ->and($seeded->name)->toBe('RI.BA. 30/60 GG F.M.')
        ->and($seeded->installments_count)->toBe(2)
        ->and($seeded->excluded_month_1)->toBe(8);
});

it('warns and keeps the current value of an unknown enum or an out of range month', function () {
    fakePaymentMethodsResponse([legacyPaymentMethodRecord(['payment_condition' => '1', 'excluded_month_1' => 13])]);

    $run = runPaymentMethodsImport();

    $method = PaymentMethod::query()->where('old_id', 70)->first();
    $warnings = collect($run->fresh()->report)->where('level', 'warning');

    expect($method->payment_condition)->toBe(PaymentCondition::Full)
        ->and($method->excluded_month_1)->toBeNull()
        ->and($warnings)->toHaveCount(2)
        ->and($run->fresh()->created_rows)->toBe(1);
});
