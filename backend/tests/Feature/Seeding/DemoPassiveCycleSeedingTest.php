<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoPurchaseInvoiceSeeder;
use Database\Seeders\DemoPurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Demo payable documents bill the order lines once (spec 0252, AC-007).
 */
uses(RefreshDatabase::class);

it('AC-007: every demo document line is linked to an order line billed only once', function () {
    User::factory()->create();
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    Registry::factory()->supplier()->create();
    PaymentMethod::factory()->create(['code' => 'riba_30_60', 'installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);

    $this->seed(DemoPurchaseOrderSeeder::class);
    $this->seed(DemoPurchaseInvoiceSeeder::class);

    $lines = PurchaseInvoiceLine::query()->get();

    expect($lines)->not->toBeEmpty()
        ->and($lines->every(fn (PurchaseInvoiceLine $line): bool => $line->purchase_order_line_id !== null))->toBeTrue()
        ->and($lines->pluck('purchase_order_line_id')->unique())->toHaveCount($lines->count())
        ->and(PurchaseOrderLine::query()->whereIn('id', $lines->pluck('purchase_order_line_id'))->count())->toBe($lines->count());
});
