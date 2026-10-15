<?php

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\Company;
use App\Models\CostType;
use App\Models\EnasarcoParameter;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\VatRate;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoCommissionSettlementSeeder;
use Database\Seeders\DemoEnasarcoParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// A full DemoDataSeeder run cannot be exercised on the SQLite test driver, so the
// two seeders run on a minimal ledger: three agents with open movements.

it('AC-019: the seeders create demo parameters, one run and drafts with some issued, and a second run keeps the same counts', function () {
    User::factory()->create(['email' => 'demo@app.com']);
    $company = Company::factory()->create();
    $movementDate = CarbonImmutable::today()->subMonthsNoOverflow(2)->toDateString();

    foreach (range(1, 3) as $ignored) {
        settlementMovement(settlementAgent(), $company, '1000.00', $movementDate);
    }

    $seed = function () {
        $this->seed(DemoEnasarcoParameterSeeder::class);
        $this->seed(DemoCommissionSettlementSeeder::class);
    };

    $seed();

    $settlements = CommissionSettlement::query()->count();
    $issued = CommissionSettlement::query()->whereIn('status', CommissionSettlementStatus::validStatuses())->count();

    expect(EnasarcoParameter::query()->pluck('year')->all())->toEqualCanonicalizing([now()->year, now()->year - 1])
        ->and(CommissionSettlementRun::query()->count())->toBe(1)
        ->and($settlements)->toBe(3)
        ->and($issued)->toBeGreaterThan(0)->toBeLessThan($settlements);

    $seed();

    expect(EnasarcoParameter::query()->count())->toBe(2)
        ->and(CommissionSettlementRun::query()->count())->toBe(1)
        ->and(CommissionSettlement::query()->count())->toBe($settlements)
        ->and(CommissionSettlement::query()->whereIn('status', CommissionSettlementStatus::validStatuses())->count())->toBe($issued);
});

it('AC-018: one issued settlement gets its document, the next is approved with a linked payable invoice, idempotently', function () {
    User::factory()->create(['email' => 'demo@app.com']);
    PaymentMethod::factory()->create();
    CostType::factory()->create();
    VatRate::factory()->create(['rate' => '0.00']);
    $company = Company::factory()->create();
    $movementDate = CarbonImmutable::today()->subMonthsNoOverflow(2)->toDateString();

    foreach (range(1, 3) as $ignored) {
        settlementMovement(settlementAgent(), $company, '1000.00', $movementDate);
    }

    $seed = function () {
        $this->seed(DemoEnasarcoParameterSeeder::class);
        $this->seed(DemoCommissionSettlementSeeder::class);
    };
    $count = fn (CommissionSettlementStatus $status): int => CommissionSettlement::query()->where('status', $status)->count();

    $seed();

    $approved = CommissionSettlement::query()->where('status', CommissionSettlementStatus::Approved)->sole();

    expect($count(CommissionSettlementStatus::DocumentReceived))->toBe(1)
        ->and($approved->purchase_invoice_id)->not->toBeNull()
        ->and(PurchaseInvoice::query()->count())->toBe(1)
        ->and(PurchaseInvoice::query()->find($approved->purchase_invoice_id)->document_number)->toBe($approved->document_number)
        ->and($approved->referent->supplier_registry_id)->not->toBeNull();

    $seed();

    expect($count(CommissionSettlementStatus::DocumentReceived))->toBe(1)
        ->and($count(CommissionSettlementStatus::Approved))->toBe(1)
        ->and(PurchaseInvoice::query()->count())->toBe(1);
});

it('AC-019: the clean DatabaseSeeder does not call the new demo seeders', function () {
    $source = file_get_contents((new ReflectionClass(DatabaseSeeder::class))->getFileName());

    expect($source)->not->toContain('DemoEnasarcoParameterSeeder')
        ->and($source)->not->toContain('DemoCommissionSettlementSeeder');
});
