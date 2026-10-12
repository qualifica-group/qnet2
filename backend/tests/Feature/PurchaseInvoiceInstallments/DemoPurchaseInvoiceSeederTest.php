<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoPurchaseInvoiceSeeder;
use Database\Seeders\DemoPurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips quietly when there are no purchase orders', function () {
    $this->seed(DemoPurchaseInvoiceSeeder::class);

    expect(PurchaseInvoice::query()->count())->toBe(0);
});

it('seeds the payable documents once with schedules, links and payments, whatever the number of runs', function () {
    User::factory()->create();
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    Registry::factory()->supplier()->create();
    PaymentMethod::factory()->create(['code' => 'riba_30_60', 'installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);

    $this->seed(DemoPurchaseOrderSeeder::class);
    $this->seed(DemoPurchaseInvoiceSeeder::class);
    $this->seed(DemoPurchaseInvoiceSeeder::class);

    $documents = PurchaseInvoice::query()->with('purchaseOrders')->orderBy('id')->get();

    expect($documents)->toHaveCount(3)
        ->and($documents->map(fn (PurchaseInvoice $document): int => $document->purchaseOrders->count())->all())->toBe([2, 1, 1])
        ->and(PurchaseInvoiceInstallment::query()->whereNotNull('paid_amount')->count())->toBeGreaterThanOrEqual(2)
        ->and($documents->every(fn (PurchaseInvoice $document): bool => bccomp((string) $document->installments()->sum('amount'), (string) $document->total_amount, 2) === 0))->toBeTrue();
});
