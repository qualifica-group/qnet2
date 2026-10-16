<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Spatie\Permission\Models\Permission;

if (! function_exists('multiQuoteReadFixture')) {
    /**
     * A multi-offer commessa with one revenue line per offer; every offer has
     * its own customer (named after $registryNames) and its own Contratto.
     *
     * @param  array<int, string>  $registryNames
     * @return array{0: WorkOrder, 1: array<int, Quote>}
     */
    function multiQuoteReadFixture(array $registryNames = ['Alfa Srl', 'Beta Spa']): array
    {
        $workOrder = WorkOrder::factory()->multiQuote()->create();
        $quotes = [];

        foreach ($registryNames as $name) {
            $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create(['name' => $name])->id]);
            $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
            Contract::factory()->create(['quote_id' => $quote->id]);
            $workOrder->quoteLines()->attach(QuoteLine::factory()->create(['quote_id' => $quote->id])->id);
            $quotes[] = $quote;
        }

        return [$workOrder, $quotes];
    }
}

if (! function_exists('multiQuoteProgramActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function multiQuoteProgramActor(array $abilities = ['contracts.program', 'work-orders.view']): User
    {
        foreach (['contracts.viewAny', 'contracts.view', 'contracts.program', 'work-orders.viewAny', 'work-orders.view', 'work-orders.update', 'work-orders.viewAll'] as $name) {
            Permission::findOrCreate($name);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($abilities);

        return $user;
    }
}

if (! function_exists('multiQuoteProgramContract')) {
    /**
     * A won (ClosedWon group) Contratto with its own customer and one revenue
     * line of $product (a flagged-branch product by default).
     *
     * @return array{0: Contract, 1: QuoteLine}
     */
    function multiQuoteProgramContract(?Product $product = null, string $status = 'validated'): array
    {
        $product ??= Product::factory()->create(['category_id' => ProductCategory::factory()->create(['multi_quote_work_order' => true])->id]);
        $opportunity = Opportunity::factory()->create(['registry_id' => Registry::factory()->create()->id]);
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
        $contract = Contract::factory()->create([
            'quote_id' => $quote->id,
            'contract_status_id' => ContractStatus::where('system_key', $status)->sole()->id,
        ]);
        $line = QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => $product->id, 'sort_order' => 1]);

        return [$contract, $line];
    }
}

if (! function_exists('multiQuoteProgramPayload')) {
    /**
     * Body of POST /api/contracts/multi-quote-work-orders (spec 0267).
     *
     * @param  array<int, int>  $contractIds
     * @param  array<int, int>  $lineIds
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function multiQuoteProgramPayload(array $contractIds, array $lineIds, array $overrides = []): array
    {
        return [...workOrderRequiredFields(), 'type' => 'processing', 'contract_ids' => $contractIds, 'quote_line_ids' => $lineIds, ...$overrides];
    }
}
