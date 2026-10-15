<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Opportunity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\VatRate;
use App\Models\WorkOrder;

if (! function_exists('multiQuoteWorkOrder')) {
    /**
     * A multi-offer work order covering one consultancy line per offer. Every
     * offer has its own company, site, customer registry and payment method.
     *
     * @return array{0: WorkOrder, 1: Quote, 2: Quote}
     */
    function multiQuoteWorkOrder(): array
    {
        $typology = ProductTypology::query()->firstOrCreate(['code' => 'consultancy'], ['name' => 'consultancy', 'proforma_kind' => 'consultancy']);
        $workOrder = WorkOrder::factory()->multiQuote()->create();
        $quotes = [];

        foreach ([1, 2] as $position) {
            $quote = Quote::factory()->create([
                'opportunity_id' => Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id])->id,
                'company_id' => Company::factory()->create()->id,
                'company_site_id' => CompanySite::factory()->create()->id,
                'payment_method_id' => PaymentMethod::factory()->create(['installments_count' => $position, 'payment_days' => 30, 'days_between_installments' => 30])->id,
            ]);
            $product = Product::factory()->create(['product_typology_id' => $typology->id]);
            $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id])->id);
            $quotes[] = $quote;
        }

        return [$workOrder, ...$quotes];
    }
}

if (! function_exists('multiQuotePayload')) {
    /**
     * An invoice write payload with the defaults of the given offer and one line.
     *
     * @return array<string, mixed>
     */
    function multiQuotePayload(Quote $quote, int $quoteLineId): array
    {
        return [
            'document_date' => '2026-03-15',
            'company_id' => $quote->company_id,
            'customer_registry_id' => $quote->opportunity->registry_id,
            'payment_method_id' => $quote->payment_method_id,
            'lines' => [[
                'quote_line_id' => $quoteLineId,
                'description' => 'Consulting',
                'quantity' => '1',
                'unit_price' => '100.00',
                'vat_rate_id' => VatRate::factory()->create(['rate' => '22.00'])->id,
            ]],
        ];
    }
}
