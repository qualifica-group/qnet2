<?php

namespace Database\Seeders\ManagementControl;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\PurchaseRequestPriority;
use App\Services\PurchaseOrders\PurchaseOrderFromRequestLinesService;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use App\Services\PurchaseRequests\PurchaseRequestLineStatusService;
use App\Services\PurchaseRequests\PurchaseRequestService;
use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * The structure purchases that go through the full passive flow, every quarter:
 * a purchase request (RDA) by a function, its lines approved two days later, the
 * order (ODA) generated from them, approved and sent, then billed by the supplier.
 */
final class McPurchaseRequests
{
    private const array QUARTER_START_MONTHS = [1, 4, 7, 10];

    private const int APPROVE_AFTER_DAYS = 2;

    private const int ORDER_AFTER_DAYS = 4;

    private const int BILL_AFTER_DAYS = 20;

    public function __construct(
        private readonly PurchaseRequestService $requests,
        private readonly PurchaseRequestLineStatusService $lineStatuses,
        private readonly PurchaseOrderFromRequestLinesService $fromLines,
        private readonly PurchaseOrderStatusService $orderStatuses,
        private readonly McPayables $payables,
    ) {}

    public function schedule(McTimeline $timeline, McContext $context, Generator $faker): void
    {
        $functions = BusinessFunction::query()->orderBy('id')->get(['id', 'manager_id']);
        $operationalSiteId = OperationalSite::query()->orderBy('id')->value('id');
        $priorityId = PurchaseRequestPriority::query()->where('is_active', true)->orderBy('sort_order')->value('id');

        for ($month = CarbonImmutable::parse(McCatalogue::PERIOD_START); $month->toDateString() <= McCatalogue::PERIOD_END; $month = $month->addMonth()) {
            if (! in_array($month->month, self::QUARTER_START_MONTHS, true)) {
                continue;
            }

            foreach (McCatalogue::STRUCTURE_REQUESTS as [$subject, $costType, $supplierName, $lines]) {
                $requestedOn = McTimeline::nextWorkingDay($month->addDays($faker->numberBetween(4, 20)));
                $function = $faker->randomElement($functions->all());

                $timeline->at($requestedOn->toDateString(), fn (string $date) => $this->request(
                    $timeline, $context, $faker, $requestedOn, $subject, $context->costTypes[$costType], $context->suppliers[$supplierName]->id, $lines,
                    ['business_function_id' => $function->id, 'function_manager_id' => $function->manager_id ?? $context->actor->id, 'operational_site_id' => $operationalSiteId, 'purchase_request_priority_id' => $priorityId],
                ));
            }
        }
    }

    /**
     * @param  list<array{0: string, 1: int, 2: float}>  $lines
     * @param  array<string, int|null>  $header
     */
    private function request(McTimeline $timeline, McContext $context, Generator $faker, CarbonImmutable $requestedOn, string $subject, int $costTypeId, int $supplierId, array $lines, array $header): void
    {
        $company = $context->companies[0];
        $request = $this->requests->create(new PurchaseRequestData([
            ...$header,
            'subject' => $subject,
            'requested_at' => $requestedOn->toDateString(),
            'requester_id' => $faker->randomElement($context->supervisors->all())->id,
            'company_id' => $company['company_id'],
            'company_site_id' => $company['company_site_id'],
        ], array_map(fn (array $line): array => [
            'product_id' => null, 'description' => $line[0], 'supplier_id' => $supplierId, 'reason' => null, 'unit_of_measure_id' => null,
            'quantity' => (string) $line[1], 'unit_price' => (string) round($line[2] * $faker->randomFloat(2, 0.95, 1.08), 2), 'vat_rate_id' => $context->vatRateId,
        ], $lines)), $context->actor);
        $lineIds = $request->lines->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $timeline->at($requestedOn->addDays(self::APPROVE_AFTER_DAYS)->toDateString(), fn () => $this->lineStatuses->change($context->actor, $lineIds, PurchaseRequestLineStatus::Approved, null));
        $timeline->at($requestedOn->addDays(self::ORDER_AFTER_DAYS)->toDateString(), function (string $date) use ($timeline, $context, $faker, $lineIds, $costTypeId, $requestedOn): void {
            foreach ($this->fromLines->handle($context->actor, $lineIds, $costTypeId, $context->supplierPaymentMethodId, $date)['purchase_orders'] as $order) {
                foreach ([PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier] as $status) {
                    $order = $this->orderStatuses->change($order, $status, $context->actor);
                }

                $billOn = McTimeline::nextWorkingDay($requestedOn->addDays(self::BILL_AFTER_DAYS))->toDateString();
                $timeline->at($billOn, fn (string $on) => $this->payables->billOrder($timeline, $context, $faker, $order, $on));
            }
        });
    }
}
