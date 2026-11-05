<?php

declare(strict_types=1);

use App\Models\ChartAccount;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\ProformaRequest;
use App\Models\RevenueType;

if (! function_exists('revenueSubAccount')) {
    /**
     * A revenue sub-account under a fresh revenue group and account.
     *
     * @param  array<string, mixed>  $attributes
     */
    function revenueSubAccount(array $attributes = []): RevenueType
    {
        return RevenueType::factory()->create($attributes + ['chart_account_id' => revenueGroupAccountPair()[1]->id]);
    }
}

if (! function_exists('institutionCommissionDefault')) {
    /**
     * The only sub-account flagged as the institution-commission default (the seeded one is unflagged).
     */
    function institutionCommissionDefault(): RevenueType
    {
        RevenueType::query()->update(['is_institution_commission_default' => false]);

        return revenueSubAccount(['is_institution_commission_default' => true]);
    }
}

if (! function_exists('invoiceLineOf')) {
    /**
     * An unclassified line on a new invoice, institution (proforma request of that kind) or not.
     *
     * @param  array<string, mixed>  $line
     */
    function invoiceLineOf(?int $productId, bool $institution = false, array $line = []): InvoiceLine
    {
        $invoice = Invoice::factory()->create([
            'proforma_request_id' => $institution ? ProformaRequest::factory()->create(['kind' => 'institution'])->id : null,
        ]);

        return InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'product_id' => $productId, ...$line]);
    }
}

if (! function_exists('revenueGroupAccountPair')) {
    /**
     * @return array{0: ChartAccount, 1: ChartAccount} [group, account]
     */
    function revenueGroupAccountPair(): array
    {
        $group = ChartAccount::factory()->revenue()->create();

        return [$group, ChartAccount::factory()->account($group)->create()];
    }
}
