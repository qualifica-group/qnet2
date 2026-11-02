<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseRequestCapability;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Services\PurchaseRequests\PurchaseRequestCapabilityResolver;
use App\Services\PurchaseRequests\PurchaseRequestLineLocker;
use App\Services\PurchaseRequests\PurchaseRequestLineStatusService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Generation of purchase orders from approved purchase request lines (spec
 * 0210, D-17). All or nothing, in one transaction with the request lines
 * locked: one draft order per (supplier, company, site, operational site)
 * group, then the lines go to `ordered` through the very same status service
 * as every other change (log, bulk group id, automatic closure) and take the
 * order code as ODA reference.
 */
class PurchaseOrderFromRequestLinesService
{
    private const int DESCRIPTION_MAX_LENGTH = 255;

    /** Capabilities that let an actor order a line (spec 0209, D-2). */
    private const array ORDERING_CAPABILITIES = [PurchaseRequestCapability::Fulfill, PurchaseRequestCapability::Manage];

    public function __construct(
        private readonly PurchaseRequestLineLocker $locker,
        private readonly PurchaseRequestCapabilityResolver $capabilities,
        private readonly PurchaseRequestLineStatusService $statuses,
        private readonly PurchaseOrderService $orders,
        private readonly PurchaseRequestLineLinker $linker,
    ) {}

    /**
     * The returned orders come with `supplier` and `lines_count` loaded.
     *
     * @param  array<int, int>  $lineIds  in request order (the 422 keys follow it)
     * @return array{purchase_orders: array<int, PurchaseOrder>, closed_purchase_request_ids: array<int, int>}
     */
    public function handle(User $actor, array $lineIds, int $costTypeId, ?int $paymentMethodId, string $orderDate): array
    {
        return DB::transaction(function () use ($actor, $lineIds, $costTypeId, $paymentMethodId, $orderDate): array {
            // Step 1: lock the lines and their requests
            $lines = $this->locker->lines($lineIds);
            $requests = $this->locker->requests($lines);

            // Step 2: refuse the whole batch if the actor, a request or a line is not eligible
            $this->assertEligible($actor, $lineIds, $lines, $requests);

            // Step 3: one order per group, in the order the lines were given
            $created = [];
            $codes = [];

            foreach ($this->groups($lineIds, $lines, $requests) as $group) {
                $order = $this->createOrder($group, $requests, $costTypeId, $paymentMethodId, $orderDate, $actor);
                $created[] = $order;

                foreach ($group as $line) {
                    $codes[$line->id] = $order->code();
                }
            }

            // Step 4: the lines become ordered (one mass change), then point at their order
            $result = $this->statuses->change($actor, $lineIds, PurchaseRequestLineStatus::Ordered, null);
            $this->linker->link($codes);

            return [
                'purchase_orders' => (new Collection($created))->load('supplier:id,name')->loadCount('lines')->all(),
                'closed_purchase_request_ids' => $result['closed_purchase_request_ids'],
            ];
        });
    }

    /**
     * 403 when the actor cannot order a line, 409 on a closed request, 422 on a
     * line that is not approved, has no supplier or is already on an order.
     *
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @param  Collection<int, PurchaseRequest>  $requests
     */
    private function assertEligible(User $actor, array $lineIds, Collection $lines, Collection $requests): void
    {
        $ordered = array_map(fn (int $id): PurchaseRequestLine => $lines->get($id) ?? abort(404), $lineIds);

        foreach ($ordered as $line) {
            $held = $this->capabilities->capabilities($actor, $requests->get($line->purchase_request_id));

            if (array_filter($held, static fn (PurchaseRequestCapability $capability): bool => in_array($capability, self::ORDERING_CAPABILITIES, true)) === []) {
                throw new AuthorizationException(__('You do not hold a role on this purchase request that allows ordering its lines.'));
            }
        }

        foreach ($ordered as $line) {
            if ($requests->get($line->purchase_request_id)->isClosed()) {
                abort(409, 'This purchase request is closed and can no longer be changed.');
            }
        }

        $linked = $this->linkedLineIds($lines);
        $errors = [];

        foreach ($ordered as $index => $line) {
            $message = match (true) {
                $line->status !== PurchaseRequestLineStatus::Approved => 'Only approved lines can generate a purchase order.',
                $line->supplier_id === null => 'A supplier is required to generate a purchase order.',
                in_array($line->id, $linked, true) => 'The line is already linked to a purchase order.',
                default => null,
            };

            if ($message !== null) {
                $errors["line_ids.{$index}"] = [__($message)];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @return array<int, int>
     */
    private function linkedLineIds(Collection $lines): array
    {
        return DB::table('purchase_order_lines')
            ->whereIn('purchase_request_line_id', $lines->keys())
            ->pluck('purchase_request_line_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Lines grouped by supplier and by the company, site and operational site
     * of their request, in first-appearance order.
     *
     * @param  array<int, int>  $lineIds
     * @param  Collection<int, PurchaseRequestLine>  $lines
     * @param  Collection<int, PurchaseRequest>  $requests
     * @return array<string, array<int, PurchaseRequestLine>>
     */
    private function groups(array $lineIds, Collection $lines, Collection $requests): array
    {
        $groups = [];

        foreach ($lineIds as $id) {
            $line = $lines->get($id);
            $request = $requests->get($line->purchase_request_id);
            $key = implode('|', [$line->supplier_id, $request->company_id, $request->company_site_id, $request->operational_site_id]);
            $groups[$key][] = $line;
        }

        return $groups;
    }

    /**
     * @param  array<int, PurchaseRequestLine>  $group
     * @param  Collection<int, PurchaseRequest>  $requests
     */
    private function createOrder(array $group, Collection $requests, int $costTypeId, ?int $paymentMethodId, string $orderDate, User $actor): PurchaseOrder
    {
        $groupRequests = collect($group)->map(fn (PurchaseRequestLine $line): PurchaseRequest => $requests->get($line->purchase_request_id));
        $first = $groupRequests->first();

        $header = [
            'supplier_id' => $group[0]->supplier_id,
            'company_id' => $first->company_id,
            'company_site_id' => $first->company_site_id,
            'operational_site_id' => $first->operational_site_id,
            'order_date' => $orderDate,
            'description' => $this->description($groupRequests->pluck('id')->unique()),
            'payment_method_id' => $paymentMethodId,
            'customer_id' => $this->sharedValue($groupRequests, 'customer_id'),
            'work_order_id' => $this->sharedValue($groupRequests, 'work_order_id'),
        ];

        $lines = array_map(fn (PurchaseRequestLine $line): array => [
            'product_id' => $line->product_id,
            'description' => $line->description,
            'cost_type_id' => $costTypeId,
            'unit_of_measure_id' => $line->unit_of_measure_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'vat_rate_id' => $line->vat_rate_id,
            'work_order_id' => $requests->get($line->purchase_request_id)->work_order_id,
            'purchase_request_line_id' => $line->id,
        ], $group);

        return $this->orders->createOne($header, $lines, null, $actor);
    }

    /**
     * @param  SupportCollection<int, int>  $ids
     */
    private function description(SupportCollection $ids): string
    {
        $text = 'Da RDA '.$ids->map(fn (mixed $id): string => "#{$id}")->implode(', ');

        return Str::limit($text, self::DESCRIPTION_MAX_LENGTH, '...');
    }

    /**
     * The value every request of the group agrees on, null when they differ.
     *
     * @param  SupportCollection<int, PurchaseRequest>  $requests
     */
    private function sharedValue(SupportCollection $requests, string $attribute): ?int
    {
        $values = $requests->pluck($attribute)->unique()->values();

        return $values->count() === 1 && $values->first() !== null ? (int) $values->first() : null;
    }
}
