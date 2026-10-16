<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\DataObjects\ManagementControl\CashFlowFilters;
use App\Models\PaymentMethod;
use App\Services\Invoices\InstallmentScheduleCalculator;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use App\Services\Purchasing\CommittedOrderLineQuery;
use Carbon\CarbonImmutable;

/**
 * Expected payments of the open commitments (spec 0270, D-2): the committed
 * amount of the order lines, summed in SQL by (company, payment method, order
 * date); the expected date of each group, which is few, is then the first due
 * date of the order's payment method from the order date (the order date
 * itself without method). The document type does not apply to orders.
 */
final class CommittedFlowAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'purchase_orders.company_id',
        'company_site' => 'purchase_orders.company_site_id',
        'operational_site' => 'purchase_orders.operational_site_id',
    ];

    public function __construct(
        private readonly CommittedOrderLineQuery $lines,
        private readonly InstallmentScheduleCalculator $calculator,
    ) {}

    /**
     * @return array<int, array<string, int>> cents by bucket by company id (0 = none)
     */
    public function cents(CashFlowFilters $filters, string $today): array
    {
        // Step 1: committed cents by (company, method, order date)
        $amount = CommittedOrderLineQuery::COMMITTED_AMOUNT;
        $groups = FlowSupport::scope($this->lines->lines(), $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)
            ->selectRaw("purchase_orders.company_id AS group_company_id, purchase_orders.payment_method_id AS group_method_id, purchase_orders.order_date AS group_order_date, SUM({$amount}) AS total")
            ->groupBy('purchase_orders.company_id', 'purchase_orders.payment_method_id', 'purchase_orders.order_date')
            ->having('total', '>', 0)
            ->get();

        // Step 2: the expected date of each group, from the payment methods loaded once
        $methods = PaymentMethod::query()->whereIn('id', $groups->pluck('group_method_id')->filter()->unique()->values()->all())->get()->keyBy('id');
        $cents = [];
        foreach ($groups as $group) {
            $orderDate = CarbonImmutable::parse((string) $group->group_order_date)->startOfDay();
            $method = $group->group_method_id === null ? null : $methods->get((int) $group->group_method_id);
            $expected = $method === null ? $orderDate : $this->calculator->firstDueDate($orderDate, $method);
            $bucket = $expected->toDateString() < $today ? FlowSupport::BACKLOG : $expected->format('Y-m');
            $cents[(int) $group->group_company_id][$bucket] = ($cents[(int) $group->group_company_id][$bucket] ?? 0) + ExposureSupport::cents($group->total);
        }

        return $cents;
    }
}
