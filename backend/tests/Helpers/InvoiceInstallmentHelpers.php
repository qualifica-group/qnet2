<?php

declare(strict_types=1);

use App\Models\CompanySite;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('installmentUserWith')) {
    /**
     * A user holding exactly the given invoice-installments abilities, plus any
     * other permission names (e.g. invoices.collect).
     *
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $extra
     */
    function installmentUserWith(array $abilities, array $extra = []): User
    {
        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo(Permission::findOrCreate("invoice-installments.{$ability}", 'web'));
        }

        foreach ($extra as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }
}

if (! function_exists('installmentInvoice')) {
    /**
     * An invoice on a work order whose quote carries the given sites.
     *
     * @param  array<string, mixed>  $attributes  invoice overrides
     */
    function installmentInvoice(?Registry $customer = null, ?CompanySite $site = null, ?OperationalSite $operationalSite = null, array $attributes = []): Invoice
    {
        $quote = Quote::factory()->create([
            'company_site_id' => $site?->id,
            'operational_site_id' => $operationalSite?->id,
        ]);
        $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);

        return Invoice::factory()->create($attributes + [
            'customer_registry_id' => ($customer ?? Registry::factory()->create())->id,
            'quote_id' => $quote->id,
            'work_order_id' => $workOrder->id,
        ]);
    }
}

if (! function_exists('installmentOf')) {
    /**
     * @param  array<string, mixed>  $attributes  installment overrides
     */
    function installmentOf(Invoice $invoice, array $attributes = []): InvoiceInstallment
    {
        $next = (int) InvoiceInstallment::query()->where('invoice_id', $invoice->id)->max('sequence') + 1;

        return InvoiceInstallment::factory()->create($attributes + ['invoice_id' => $invoice->id, 'sequence' => $next]);
    }
}
