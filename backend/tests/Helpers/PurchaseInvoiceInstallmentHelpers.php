<?php

declare(strict_types=1);

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('purchaseInstallmentUserWith')) {
    /**
     * A user holding exactly the given purchase-invoice-installments abilities,
     * plus any other permission names (e.g. purchase-invoices.pay).
     *
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $extra
     */
    function purchaseInstallmentUserWith(array $abilities, array $extra = []): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("purchase-invoice-installments.{$ability}", 'web'));
        }

        foreach ($extra as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        // A test may have checked a permission before this call: drop the stale registry.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}

if (! function_exists('purchaseDocument')) {
    /**
     * A payable document of the given supplier (a new one by default).
     *
     * @param  array<string, mixed>  $attributes  document overrides
     */
    function purchaseDocument(?Registry $supplier = null, array $attributes = []): PurchaseInvoice
    {
        return PurchaseInvoice::factory()->create($attributes + [
            'supplier_id' => ($supplier ?? Registry::factory()->supplier()->create())->id,
            'document_date' => '2026-03-15',
        ]);
    }
}

if (! function_exists('purchaseInstallmentOf')) {
    /**
     * @param  array<string, mixed>  $attributes  installment overrides
     */
    function purchaseInstallmentOf(PurchaseInvoice $document, array $attributes = []): PurchaseInvoiceInstallment
    {
        $next = (int) PurchaseInvoiceInstallment::query()->where('purchase_invoice_id', $document->id)->max('sequence') + 1;

        return PurchaseInvoiceInstallment::factory()->create($attributes + ['purchase_invoice_id' => $document->id, 'sequence' => $next]);
    }
}

if (! function_exists('purchaseDocumentWithPlan')) {
    /**
     * A document with three installments of 400.00 (due 2026-04-14, 2026-05-14 and
     * 2026-06-13), on a document dated 2026-03-15.
     *
     * @param  array<string, mixed>  $attributes  document overrides
     */
    function purchaseDocumentWithPlan(array $attributes = []): PurchaseInvoice
    {
        $document = purchaseDocument(null, $attributes + ['total_amount' => '1200.00']);

        foreach (['2026-04-14', '2026-05-14', '2026-06-13'] as $dueDate) {
            purchaseInstallmentOf($document, ['due_date' => $dueDate, 'amount' => '400.00']);
        }

        return $document;
    }
}

if (! function_exists('purchasePlan')) {
    /**
     * The "sequence:amount" plan of a document, in sequence order.
     *
     * @return array<int, string>
     */
    function purchasePlan(PurchaseInvoice $document): array
    {
        return $document->installments()->get()
            ->map(fn (PurchaseInvoiceInstallment $row): string => $row->sequence.':'.$row->amount)
            ->all();
    }
}

if (! function_exists('payInstallment')) {
    /**
     * PUT the payment of an installment.
     *
     * @param  array<string, mixed>  $extra  payload overrides / additions
     */
    function payInstallment(int $installmentId, string $amount, array $extra = []): TestResponse
    {
        return test()->putJson("/api/purchase-invoice-installments/{$installmentId}/payment", $extra + ['paid_amount' => $amount, 'paid_at' => '2026-04-20']);
    }
}
