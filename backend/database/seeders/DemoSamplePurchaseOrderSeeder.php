<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestStatus;
use App\Models\CompanySite;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestLine;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\PurchaseOrders\PurchaseOrderFromRequestLinesService;
use App\Services\PurchaseOrders\PurchaseOrderService;
use App\Services\PurchaseOrders\PurchaseOrderStatusService;
use App\Services\PurchaseRequests\PurchaseRequestLineStatusService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Additive purchase orders (spec 0210) for DemoAccountingSampleSeeder: half of
 * the approved, not yet ordered RDA lines become orders through the real
 * generation (the other half stays in the "to order" queue), then a batch of
 * direct orders, some lines charged to a work order. Every new order is
 * walked through PurchaseOrderStatusService to one of the states, and the RDA
 * lines of a fulfilled order are marked received.
 */
class DemoSamplePurchaseOrderSeeder extends Seeder
{
    public const int DEFAULT_ORDERS = 6;

    /**
     * Target state => the transitions that reach it from a draft (D-9 matrix).
     *
     * @var array<string, array<int, PurchaseOrderStatus>>
     */
    private const array STATUS_PATHS = [
        'draft' => [],
        'in_validation' => [PurchaseOrderStatus::InValidation],
        'approved' => [PurchaseOrderStatus::Approved],
        'sent_to_supplier' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier],
        'partially_fulfilled' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier, PurchaseOrderStatus::PartiallyFulfilled],
        'fulfilled' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::SentToSupplier, PurchaseOrderStatus::Fulfilled],
        'cancelled' => [PurchaseOrderStatus::Cancelled],
    ];

    /** @var array<int, array{0: string, 1: string, 2: string}> [description, quantity, unit price] */
    private const array DIRECT_LINES = [
        ['Consulenza specialistica', '8', '85.00'],
        ['Canone mensile sede', '1', '1200.00'],
        ['Energia elettrica', '3', '240.00'],
        ['Prestazione docente esterno', '16', '60.00'],
        ['Noleggio aula attrezzata', '2', '350.00'],
        ['Stampa attestati', '50', '1.80'],
        ['Servizio catering', '25', '14.00'],
    ];

    public function __construct(
        private readonly PurchaseOrderFromRequestLinesService $fromRequestLines,
        private readonly PurchaseOrderService $orders,
        private readonly PurchaseOrderStatusService $statuses,
        private readonly PurchaseRequestLineStatusService $lineStatuses,
    ) {}

    public function run(User $actor, int $orders = self::DEFAULT_ORDERS): void
    {
        // Step 1: the reference data every order hangs on
        $costTypeIds = CostType::query()->where('is_active', true)->orderBy('sort_order')->pluck('id');
        $methodId = PaymentMethod::query()->orderBy('id')->value('id');
        $sites = CompanySite::query()->whereNotNull('company_id')->orderBy('id')->get(['id', 'company_id']);
        $supplierIds = Registry::query()->where('is_supplier', true)->orderBy('id')->pluck('id');
        $vatRateId = VatRate::query()->orderByDesc('rate')->value('id');

        if ($costTypeIds->isEmpty() || $sites->isEmpty() || $supplierIds->isEmpty() || $vatRateId === null) {
            $this->command?->warn('Purchase orders skipped: cost types, company sites, suppliers or VAT rates are missing.');

            return;
        }

        // Step 2: orders generated from half of the approved RDA lines
        $created = collect($this->fromRequests($actor, $costTypeIds->first(), $methodId));

        // Step 3: direct orders
        $workOrderIds = WorkOrder::query()->orderByDesc('id')->limit(20)->pluck('id');

        foreach (range(0, max(0, $orders - 1)) as $index) {
            $created->push($this->direct($index, $actor, $sites->random(), $supplierIds->random(), $costTypeIds, $vatRateId, $methodId, $workOrderIds));
        }

        // Step 4: walk every new order to its state, then receive the RDA lines of the fulfilled ones
        $targets = array_keys(self::STATUS_PATHS);

        foreach ($created->values() as $position => $order) {
            $this->walk($order, $targets[$position % count($targets)], $actor);
        }

        $this->receive($created, $actor);
    }

    /**
     * @return array<int, PurchaseOrder>
     */
    private function fromRequests(User $actor, int $costTypeId, ?int $methodId): array
    {
        $lineIds = PurchaseRequestLine::query()
            ->where('status', PurchaseRequestLineStatus::Approved)
            ->whereNotNull('supplier_id')
            ->whereDoesntHave('purchaseOrderLine')
            ->whereHas('purchaseRequest', fn ($query) => $query->where('status', '!=', PurchaseRequestStatus::Closed))
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $id, int $position): bool => $position % 2 === 0)
            ->values()
            ->all();

        if ($lineIds === []) {
            return [];
        }

        return $this->fromRequestLines->handle($actor, $lineIds, $costTypeId, $methodId, now()->toDateString())['purchase_orders'];
    }

    /**
     * @param  Collection<int, int>  $costTypeIds
     * @param  Collection<int, int>  $workOrderIds
     */
    private function direct(int $index, User $actor, CompanySite $site, int $supplierId, Collection $costTypeIds, int $vatRateId, ?int $methodId, Collection $workOrderIds): PurchaseOrder
    {
        $lines = collect(self::DIRECT_LINES)->shuffle()->take(random_int(1, 3))->values()
            ->map(fn (array $line, int $position): array => [
                'id' => null, 'product_id' => null, 'description' => $line[0], 'cost_type_id' => $costTypeIds->random(),
                'unit_of_measure_id' => null, 'quantity' => $line[1], 'unit_price' => $line[2], 'vat_rate_id' => $vatRateId,
                // every other order charges its first line to a work order (commessa costs)
                'work_order_id' => $position === 0 && $index % 2 === 0 && $workOrderIds->isNotEmpty() ? $workOrderIds->random() : null,
            ])->all();

        return $this->orders->createOne([
            'supplier_id' => $supplierId,
            'company_id' => $site->company_id,
            'company_site_id' => $site->id,
            'order_date' => now()->subDays(random_int(0, 90))->toDateString(),
            'description' => sprintf('%s (demo)', $lines[0]['description']),
            'payment_method_id' => $methodId,
        ], $lines, null, $actor);
    }

    private function walk(PurchaseOrder $order, string $target, User $actor): void
    {
        foreach (self::STATUS_PATHS[$target] as $status) {
            $order = $this->statuses->change($order, $status, $actor);
        }
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     */
    private function receive(Collection $orders, User $actor): void
    {
        $lineIds = PurchaseRequestLine::query()
            ->where('status', PurchaseRequestLineStatus::Ordered)
            ->whereHas('purchaseOrderLine.purchaseOrder', fn ($query) => $query
                ->whereKey($orders->pluck('id'))
                ->where('status', PurchaseOrderStatus::Fulfilled))
            ->pluck('id')
            ->all();

        if ($lineIds !== []) {
            $this->lineStatuses->change($actor, $lineIds, PurchaseRequestLineStatus::Received, null);
        }
    }
}
