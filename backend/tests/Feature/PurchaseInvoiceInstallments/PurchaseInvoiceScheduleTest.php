<?php

use App\Enums\PurchaseInstallmentStatus;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseOrder;
use App\Services\PurchaseInvoices\PurchaseInvoiceInstallmentPlanner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-001: the generated residual and the SQL status agree with PurchaseInstallmentStatus, whatever is paid', function (?string $paid, string $residual, PurchaseInstallmentStatus $status) {
    $installment = PurchaseInvoiceInstallment::factory()->create(['amount' => '100.00', 'paid_amount' => $paid]);

    $sqlStatus = DB::table('purchase_invoice_installments')
        ->where('id', $installment->id)
        ->selectRaw("CASE WHEN COALESCE(paid_amount, 0) <= 0 THEN 'unpaid' ELSE 'paid' END AS status")
        ->value('status');

    expect(DB::table('purchase_invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual($residual)
        ->and($sqlStatus)->toBe($status->value)
        ->and($installment->fresh()->status())->toBe($status);
})->with([
    'unpaid' => [null, '100', PurchaseInstallmentStatus::Unpaid],
    'paid below amount' => ['40.00', '60', PurchaseInstallmentStatus::Paid],
    'paid' => ['100.00', '0', PurchaseInstallmentStatus::Paid],
    'paid above amount' => ['120.00', '-20', PurchaseInstallmentStatus::Paid],
]);

it('AC-001: keeps the residual in step when the payment changes and labels the status per locale', function () {
    $installment = PurchaseInvoiceInstallment::factory()->create(['amount' => '50.00']);
    $installment->update(['paid_amount' => '20.00']);

    expect(DB::table('purchase_invoice_installments')->where('id', $installment->id)->value('residual_amount'))->toEqual('30')
        ->and(PurchaseInstallmentStatus::values())->toBe(['unpaid', 'paid']);

    app()->setLocale('it');

    expect(PurchaseInstallmentStatus::Unpaid->label())->toBe('Da pagare')
        ->and(PurchaseInstallmentStatus::Paid->label())->toBe('Pagata');
});

it('AC-001: the document cascades its installments and links, and the pivot is unique per pair', function () {
    $document = purchaseDocumentWithPlan();
    $order = PurchaseOrder::factory()->create();
    $document->purchaseOrders()->attach($order->id);

    expect(fn () => $document->purchaseOrders()->attach($order->id))->toThrow(QueryException::class);

    $document->delete();

    expect(PurchaseInvoiceInstallment::query()->count())->toBe(0)
        ->and(DB::table('purchase_invoice_purchase_order')->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(1);
});

it('AC-002: a 3-installment payment method generates 3 installments summing to the total, with the method code copied', function () {
    $method = PaymentMethod::factory()->create([
        'payment_method_code' => 'RB30', 'installments_count' => 3, 'payment_days' => 30, 'days_between_installments' => 30,
    ]);
    $document = PurchaseInvoice::factory()->create([
        'payment_method_id' => $method->id, 'document_date' => '2026-03-15',
        'net_amount' => '1000.00', 'vat_amount' => '220.00', 'total_amount' => '1220.00',
    ]);

    $installments = app(PurchaseInvoiceInstallmentPlanner::class)->generate($document);

    expect($installments)->toHaveCount(3)
        ->and($installments->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and($installments->pluck('due_date')->map->toDateString()->all())->toBe(['2026-04-14', '2026-05-14', '2026-06-13'])
        ->and($installments->pluck('payment_method_code')->unique()->all())->toBe(['RB30'])
        ->and(array_reduce($installments->pluck('amount')->all(), fn (string $sum, string $amount): string => bcadd($sum, $amount, 2), '0.00'))->toBe('1220.00');

    // Generating again replaces the schedule instead of stacking a second one
    expect(app(PurchaseInvoiceInstallmentPlanner::class)->generate($document))->toHaveCount(3)
        ->and(PurchaseInvoiceInstallment::query()->count())->toBe(3);
});

it('AC-002: an unusable payment method configuration is a 422 on payment_method_id', function () {
    $method = PaymentMethod::factory()->create(['installments_count' => 1, 'vat_allocation' => 'vat_first']);
    $document = PurchaseInvoice::factory()->create(['payment_method_id' => $method->id]);

    expect(fn () => app(PurchaseInvoiceInstallmentPlanner::class)->generate($document))->toThrow(ValidationException::class);
});

it('AC-013: deleting a purchase order linked to a payable document is a 409 and the order survives', function () {
    Sanctum::actingAs(purchaseOrderUserWith(['view', 'delete']));
    $linked = purchaseOrderWithLines();
    $free = purchaseOrderWithLines();
    purchaseDocument()->purchaseOrders()->attach($linked->id);

    $this->deleteJson("/api/purchase-orders/{$linked->id}")
        ->assertStatus(409)->assertJsonPath('message', 'This purchase order is linked to a purchase invoice.');
    $this->deleteJson("/api/purchase-orders/{$free->id}")->assertOk();

    expect(PurchaseOrder::query()->whereKey($linked->id)->exists())->toBeTrue()
        ->and(PurchaseOrder::query()->whereKey($free->id)->exists())->toBeFalse();
});
