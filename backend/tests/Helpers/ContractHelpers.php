<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('contractLifecycleRevenueProduct')) {
    /**
     * Spec 0102: POST /api/quotes now requires at least one offer_lines row.
     * A category with an EFFECTIVE business function so the auto-add
     * coverage path never trips the 422 guard (OpportunityProductLineCoverage).
     */
    function contractLifecycleRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

if (! function_exists('contractScopedUserWith')) {
    /**
     * An actor holding the given `contracts.*` abilities (spec 0316). Tests
     * that are not about record visibility add `viewAll` to keep seeing
     * every contract (REQUIREMENT CHANGED D-1).
     *
     * @param  array<int, string>  $abilities
     */
    function contractScopedUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate', 'viewSite', 'viewAll'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('inForceContract')) {
    /**
     * Spec 0360: a contract on `$statusKey` (a ContractStatus system_key, 'validated' = closed_won)
     * whose won offer carries `$lineCount` REVENUE rows whose products the opportunity covers.
     *
     * @return array{contract: Contract, quote: Quote, lines: array<int, QuoteLine>, product: Product}
     */
    function inForceContract(string $statusKey = 'validated', int $lineCount = 2): array
    {
        $product = contractLifecycleRevenueProduct();
        $opportunity = Opportunity::factory()->create();
        OpportunityProductLine::factory()->for($opportunity)->create([
            'business_function_id' => $product->category->business_function_id,
            'product_category_id' => $product->category_id,
        ]);
        $quote = Quote::factory()->for($opportunity)->create([
            'quote_workflow_status_id' => QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'closed_won')->value('id'),
        ]);
        $lines = [];

        foreach (range(0, $lineCount - 1) as $index) {
            $lines[] = QuoteLine::factory()->create([
                'quote_id' => $quote->id,
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100,
                'net_amount' => 200,
                'total_amount' => 200,
                'sort_order' => $index,
            ]);
        }

        $contract = Contract::factory()->for($quote)->create([
            'contract_status_id' => ContractStatus::where('system_key', $statusKey)->sole()->id,
        ]);

        return ['contract' => $contract, 'quote' => $quote, 'lines' => $lines, 'product' => $product];
    }
}

if (! function_exists('assignLineToWorkOrder')) {
    /** Spec 0360: links `$line` to a new work order of its quote and returns it. */
    function assignLineToWorkOrder(QuoteLine $line, ?string $code = null): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create(array_filter(['quote_id' => $line->quote_id, 'code' => $code]));
        $line->workOrders()->attach($workOrder->id);

        return $workOrder;
    }
}

if (! function_exists('inForceRow')) {
    /**
     * Spec 0360: the PATCH row of a stored line, resubmitted as is unless overridden.
     *
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    function inForceRow(QuoteLine $line, array $override = []): array
    {
        return [
            'id' => $line->id,
            'product_id' => $line->product_id,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'vat_rate_id' => $line->vat_rate_id,
            ...$override,
        ];
    }
}
