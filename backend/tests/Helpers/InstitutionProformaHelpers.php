<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\WorkOrder;

if (! function_exists('institutionOffer')) {
    /**
     * An offer with a fixed code issued by `$company`, with its own customer and payment method (spec 0288).
     */
    function institutionOffer(Company $company, string $code): Quote
    {
        return Quote::factory()->create([
            'code' => $code,
            'company_id' => $company->id,
            'opportunity_id' => Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id])->id,
            'payment_method_id' => PaymentMethod::factory()->create()->id,
        ]);
    }
}

if (! function_exists('institutionLine')) {
    /**
     * Attach to the work order a revenue line of `$quote` whose product has the `$kind` typology and `$supplier`.
     *
     * @param  array<string, mixed>  $line  QuoteLine overrides (quantity, unit_price, net_amount, vat_rate_id...)
     */
    function institutionLine(WorkOrder $workOrder, Quote $quote, ?Registry $supplier, string $kind = 'institution', array $line = [], ?Product $product = null): QuoteLine
    {
        $typology = ProductTypology::query()->firstOrCreate(['code' => $kind], ['name' => $kind, 'proforma_kind' => $kind]);
        $product ??= Product::factory()->create(['product_typology_id' => $typology->id, 'supplier_id' => $supplier?->id]);
        $quoteLine = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, ...$line]);
        $workOrder->quoteLines()->attach($quoteLine->id);

        return $quoteLine;
    }
}
