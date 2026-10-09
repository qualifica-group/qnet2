<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\DataObjects\PurchaseRequests\PurchaseRequestData;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic of the purchase requests (spec 0208): create, full edit with
 * line sync, delete. Amounts are always computed here (D-12), never by the
 * client; a line is editable only while pending approval (D-10) and deletable
 * only while pending or rejected (D-11).
 */
class PurchaseRequestService
{
    /** Relations PurchaseRequestResource reads (lazy loading is forbidden). */
    public const array RESOURCE_RELATIONS = [
        'requester:id,name',
        'functionManager:id,name',
        'customer:id,name',
        'supplier:id,name',
        'workOrder:id,code,title',
        'company:id,denomination',
        'companySite:id,name',
        'operationalSite.addresses.city',
        'businessFunction:id,name',
        'createdBy:id,name',
        'closedBy:id,name',
        'lines.product:id,name',
        'lines.unitOfMeasure:id,name,symbol',
        'lines.vatRate:id,name,rate',
        'lines.approvedBy:id,name',
    ];

    /** Line states a line may be deleted from (D-11). */
    private const array DELETABLE_STATUSES = [PurchaseRequestLineStatus::PendingApproval, PurchaseRequestLineStatus::Rejected];

    /** Line states that block the deletion of the whole request (D-11). */
    private const array BLOCKING_STATUSES = [PurchaseRequestLineStatus::Ordered, PurchaseRequestLineStatus::Received];

    public function __construct(
        private readonly PurchaseRequestAmountCalculator $amounts,
        private readonly PurchaseRequestNotifier $notifier,
    ) {}

    public function create(PurchaseRequestData $data, User $actor): PurchaseRequest
    {
        $purchaseRequest = DB::transaction(function () use ($data, $actor): PurchaseRequest {
            // Step 1: header (created_by is never client input)
            $purchaseRequest = new PurchaseRequest($data->header);
            $purchaseRequest->forceFill(['created_by' => $actor->id])->save();

            // Step 2: lines, all born pending approval, then the totals
            $rates = $this->vatRates($data->lines);

            foreach ($data->lines as $index => $line) {
                $this->createLine($purchaseRequest, $line, $index + 1, $rates);
            }

            $this->refreshTotals($purchaseRequest);

            return $purchaseRequest;
        });

        // Step 3: tell the function manager once the request is committed
        $this->notifier->created($purchaseRequest, $actor);

        return $this->detail($purchaseRequest);
    }

    public function update(PurchaseRequest $purchaseRequest, PurchaseRequestData $data, User $actor): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $data, $actor): PurchaseRequest {
            // Step 1: serialize on the request and refuse a closed one
            $locked = PurchaseRequest::query()->whereKey($purchaseRequest->getKey())->lockForUpdate()->firstOrFail();
            $this->assertOpen($locked);

            // Step 2: check the line payload against the stored lines before writing anything
            $existing = $locked->lines()->lockForUpdate()->get()->keyBy('id');
            $rates = $this->vatRates($data->lines);
            $this->assertLinesAreValid($existing, $data->lines, $rates);
            $removed = $this->removedLines($existing, $data->lines);
            $this->assertRemovalAllowed($removed, $actor);

            // Step 3: header, then delete / update / create the lines
            $locked->fill($data->header)->save();
            $removed->each(fn (PurchaseRequestLine $line) => $line->delete());

            foreach ($data->lines as $index => $line) {
                $stored = $line['id'] === null ? null : $existing->get($line['id']);
                $stored === null
                    ? $this->createLine($locked, $line, $index + 1, $rates)
                    : $this->updateLine($stored, $line, $index + 1, $rates);
            }

            // Step 4: totals from the lines
            $this->refreshTotals($locked);

            return $this->detail($locked);
        });
    }

    /** Delete the request, its lines, their logs and attachments (409 once something was ordered or received). */
    public function delete(PurchaseRequest $purchaseRequest): void
    {
        DB::transaction(function () use ($purchaseRequest): void {
            $locked = PurchaseRequest::query()->whereKey($purchaseRequest->getKey())->lockForUpdate()->firstOrFail();
            $lines = $locked->lines()->lockForUpdate()->get();

            if ($lines->contains(fn (PurchaseRequestLine $line): bool => in_array($line->status, self::BLOCKING_STATUSES, true))) {
                abort(409, 'This purchase request has ordered or received lines and cannot be deleted.');
            }

            // One by one: the DB cascade would skip the attachment cleanup of the models
            $lines->each(fn (PurchaseRequestLine $line) => $line->delete());
            $locked->delete();
        });
    }

    public function detail(PurchaseRequest $purchaseRequest): PurchaseRequest
    {
        return $purchaseRequest->fresh(self::RESOURCE_RELATIONS);
    }

    public function assertOpen(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->isClosed()) {
            abort(409, 'This purchase request is closed and can no longer be changed.');
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $rates  vat_rate_id => rate
     */
    private function createLine(PurchaseRequest $purchaseRequest, array $line, int $position, array $rates): void
    {
        $model = new PurchaseRequestLine($this->lineContent($line, $position));
        $model->forceFill($this->lineAmounts($line, $rates) + ['purchase_request_id' => $purchaseRequest->id, 'status' => PurchaseRequestLineStatus::PendingApproval]);
        $model->save();
    }

    /**
     * Only a pending line takes new content; any other keeps its values and only its position moves.
     *
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $rates
     */
    private function updateLine(PurchaseRequestLine $stored, array $line, int $position, array $rates): void
    {
        if ($stored->status === PurchaseRequestLineStatus::PendingApproval) {
            $stored->fill($this->lineContent($line, $position))->forceFill($this->lineAmounts($line, $rates))->save();

            return;
        }

        $stored->fill(['position' => $position])->save();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function lineContent(array $line, int $position): array
    {
        $amounts = $this->amounts->line($line['quantity'], $line['unit_price'], null);

        return [
            'position' => $position,
            'product_id' => $line['product_id'],
            'description' => $line['description'],
            'reason' => $this->blankToNull($line['reason']),
            'unit_of_measure_id' => $line['unit_of_measure_id'],
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $line['vat_rate_id'],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, string>  $rates
     * @return array{taxable_amount: string, vat_amount: string, total_amount: string}
     */
    private function lineAmounts(array $line, array $rates): array
    {
        $amounts = $this->amounts->line($line['quantity'], $line['unit_price'], $rates[$line['vat_rate_id']] ?? null);

        return [
            'taxable_amount' => $amounts['taxable_amount'],
            'vat_amount' => $amounts['vat_amount'],
            'total_amount' => $amounts['total_amount'],
        ];
    }

    private function refreshTotals(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->forceFill($this->amounts->totals($purchaseRequest->lines()->get(['taxable_amount', 'vat_amount'])))->save();
    }

    /**
     * Existing lines must belong to this request (D-10) and, unless pending,
     * keep their values.
     *
     * @param  Collection<int, PurchaseRequestLine>  $existing  keyed by id
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, string>  $rates
     */
    private function assertLinesAreValid(Collection $existing, array $lines, array $rates): void
    {
        $errors = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            if ($line['id'] === null) {
                continue;
            }

            $stored = $existing->get($line['id']);

            if ($stored === null || in_array($line['id'], $seen, true)) {
                $errors["lines.{$index}.id"] = ['The line does not belong to this purchase request.'];

                continue;
            }

            $seen[] = $line['id'];

            if ($stored->status !== PurchaseRequestLineStatus::PendingApproval && $this->contentChanged($stored, $line)) {
                $errors["lines.{$index}"] = ['The line is no longer pending approval and cannot be modified.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function contentChanged(PurchaseRequestLine $stored, array $line): bool
    {
        $submitted = $this->lineContent($line, $stored->position);

        foreach (array_diff(array_keys($submitted), ['position']) as $key) {
            $current = $stored->getAttribute($key);

            if ($key === 'reason') {
                $current = $this->blankToNull($current);
            }

            if ((string) $submitted[$key] !== (string) $current) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, PurchaseRequestLine>  $existing
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, PurchaseRequestLine>
     */
    private function removedLines(Collection $existing, array $lines): Collection
    {
        $kept = array_filter(array_column($lines, 'id'));

        return $existing->reject(fn (PurchaseRequestLine $line): bool => in_array($line->id, $kept, true))->values();
    }

    /**
     * @param  Collection<int, PurchaseRequestLine>  $removed
     */
    private function assertRemovalAllowed(Collection $removed, User $actor): void
    {
        if ($removed->isEmpty()) {
            return;
        }

        if (! $actor->can('purchase-requests.deleteLine')) {
            throw new AuthorizationException;
        }

        if ($removed->contains(fn (PurchaseRequestLine $line): bool => ! in_array($line->status, self::DELETABLE_STATUSES, true))) {
            throw ValidationException::withMessages(['lines' => ['Only pending or rejected lines can be deleted.']]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, string> vat_rate_id => rate
     */
    private function vatRates(array $lines): array
    {
        $ids = array_filter(array_unique(array_column($lines, 'vat_rate_id')));

        return VatRate::query()->whereIn('id', $ids)->pluck('rate', 'id')
            ->map(fn (mixed $rate): string => (string) $rate)->all();
    }

    private function blankToNull(mixed $value): mixed
    {
        return $value === '' ? null : $value;
    }
}
