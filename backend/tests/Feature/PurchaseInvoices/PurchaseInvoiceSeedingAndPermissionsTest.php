<?php

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoPurchaseInvoiceSeeder;
use Database\Seeders\DemoPurchaseOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('AC-011: the demo documents are written with lines through the service, totals equal to the orders', function () {
    User::factory()->create();
    CompanySite::factory()->create(['company_id' => Company::factory()->create()->id]);
    Registry::factory()->supplier()->create();
    PaymentMethod::factory()->create(['code' => 'riba_30_60', 'installments_count' => 2, 'payment_days' => 30, 'days_between_installments' => 30]);

    $this->seed(DemoPurchaseOrderSeeder::class);
    $this->seed(DemoPurchaseInvoiceSeeder::class);

    $documents = PurchaseInvoice::query()->with(['lines', 'purchaseOrders'])->orderBy('id')->get();

    expect($documents)->toHaveCount(3);

    foreach ($documents as $document) {
        expect($document->lines)->not->toBeEmpty()
            ->and((float) $document->lines->sum('net_amount'))->toBe((float) $document->net_amount)
            ->and((float) $document->lines->sum('total_amount'))->toBe((float) $document->total_amount)
            ->and($document->lines->every(fn ($line): bool => $line->cost_type_id !== null))->toBeTrue()
            ->and((float) $document->total_amount)->toBe((float) $document->purchaseOrders->sum('grand_total'));
    }
});

it('AC-008: permissions:sync creates the purchase-invoices permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['create', 'update', 'delete', 'export', 'viewActivity', 'downgrade', 'pay'] as $ability) {
        expect(Permission::query()->where('name', "purchase-invoices.{$ability}")->exists())->toBeTrue($ability);
    }
});
