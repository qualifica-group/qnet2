<?php

use App\DataObjects\PaymentMethods\CreatePaymentMethodData;
use App\DataObjects\PaymentMethods\UpdatePaymentMethodData;
use App\Http\Requests\PaymentMethods\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethods\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Database\Seeders\DemoPaymentMethodSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// migration/model — AC-001..004
// ---------------------------------------------------------------------------

it('schema: the 9 contract columns exist with the right defaults, no system_key/deleted_at (AC-001)', function () {
    foreach (['id', 'name', 'code', 'description', 'payment_instructions', 'payment_days', 'sort_order', 'is_active', 'created_at', 'updated_at'] as $column) {
        expect(Schema::hasColumn('payment_methods', $column))->toBeTrue("missing column {$column}");
    }

    expect(Schema::hasColumn('payment_methods', 'system_key'))->toBeFalse()
        ->and(Schema::hasColumn('payment_methods', 'deleted_at'))->toBeFalse();

    $id = DB::table('payment_methods')->insertGetId([
        'name' => 'Default check', 'code' => 'default_check', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $row = DB::table('payment_methods')->find($id);

    expect((int) $row->sort_order)->toBe(0)
        ->and((bool) $row->is_active)->toBeTrue();
});

it('schema: name and code are unique at the DB level (AC-001)', function () {
    DB::table('payment_methods')->insert(['name' => 'Unique Name', 'code' => 'unique_name', 'created_at' => now(), 'updated_at' => now()]);

    expect(fn () => DB::table('payment_methods')->insert(['name' => 'Unique Name', 'code' => 'other_code', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('payment_methods')->insert(['name' => 'Other Name', 'code' => 'unique_name', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('migration: down() drops the table cleanly, up() recreates it (AC-002)', function () {
    $migration = require database_path('migrations/2026_07_30_130000_create_payment_methods_table.php');

    $migration->down();
    expect(Schema::hasTable('payment_methods'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('payment_methods'))->toBeTrue();
});

it('model: casts() returns payment_days/sort_order as int, is_active as bool (AC-004)', function () {
    $paymentMethod = PaymentMethod::factory()->create(['payment_days' => 15, 'sort_order' => 20, 'is_active' => true]);
    $fresh = PaymentMethod::query()->findOrFail($paymentMethod->id);

    expect($fresh->payment_days)->toBeInt()
        ->and($fresh->sort_order)->toBeInt()
        ->and($fresh->is_active)->toBeBool();
});

// ---------------------------------------------------------------------------
// sort_order is server-managed at the HTTP layer — AC-003 (whitebox)
// ---------------------------------------------------------------------------
//
// AC-012 (PaymentMethodCrudTest) already proves the BEHAVIOUR end-to-end (a
// submitted sort_order is ignored by the API). AC-003's own claim is
// narrower and textual: the DTOs never expose the key and the FormRequests
// never declare a rule for it — asserted directly here, whitebox, so a
// future accidental `sort_order` addition to a DTO/rules() array fails loud
// even if it happened to still resolve to the right HTTP status.

it('CreatePaymentMethodData::attributes() never exposes sort_order, even when submitted (AC-003)', function () {
    $data = CreatePaymentMethodData::fromValidated(['name' => 'X', 'code' => 'x', 'sort_order' => 999]);

    expect(array_keys($data->attributes()))->not->toContain('sort_order');
});

it('UpdatePaymentMethodData::submittedAttributes() never exposes sort_order, even when submitted (AC-003)', function () {
    $data = UpdatePaymentMethodData::fromValidated(['name' => 'Y', 'sort_order' => 999]);

    expect(array_keys($data->submittedAttributes()))->not->toContain('sort_order');
});

it('StorePaymentMethodRequest::rules() declares no sort_order rule (AC-003)', function () {
    expect(array_keys((new StorePaymentMethodRequest)->rules()))->not->toContain('sort_order');
});

it('UpdatePaymentMethodRequest::rules() declares no sort_order rule (AC-003)', function () {
    $paymentMethod = PaymentMethod::factory()->create();

    // rules() reads $this->route('paymentMethod'): a real FormRequest is not
    // bound to a live route outside the HTTP kernel, so a minimal Route is
    // bound manually and the raw bound parameter (a string id) is overridden
    // with the actual model, mirroring what route-model-binding hands the
    // controller in production.
    $request = UpdatePaymentMethodRequest::create('payment-methods/'.$paymentMethod->id, 'PATCH', []);
    $route = new Route('PATCH', 'payment-methods/{paymentMethod}', []);
    $route->bind($request);
    $route->setParameter('paymentMethod', $paymentMethod);
    $request->setRouteResolver(fn () => $route);

    expect(array_keys($request->rules()))->not->toContain('sort_order');
});

// ---------------------------------------------------------------------------
// DemoPaymentMethodSeeder — AC-130/131
// ---------------------------------------------------------------------------

it('DemoPaymentMethodSeeder creates at least the 6 catalogued methods with unique codes and progressive sort_order (AC-131)', function () {
    $this->seed(DemoPaymentMethodSeeder::class);

    expect(PaymentMethod::count())->toBeGreaterThanOrEqual(6);

    $codes = PaymentMethod::query()->pluck('code');
    expect($codes->unique()->count())->toBe($codes->count())
        ->and($codes->all())->toContain('bank_transfer', 'credit_card', 'cash', 'check', 'direct_debit', 'installments');

    $orders = PaymentMethod::query()->orderBy('sort_order')->pluck('sort_order');
    expect($orders->values()->all())->toBe($orders->sort()->values()->all());
});

it('DemoPaymentMethodSeeder run twice leaves the same row count, idempotent via code (AC-130)', function () {
    $this->seed(DemoPaymentMethodSeeder::class);
    $countAfterFirst = PaymentMethod::count();

    $this->seed(DemoPaymentMethodSeeder::class);

    expect(PaymentMethod::count())->toBe($countAfterFirst);
});
