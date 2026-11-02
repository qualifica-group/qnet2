<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Models\VatRate;
use App\Support\Amounts\LineAmountCalculator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Writes the lines of a purchase request from the submitted payload (spec 0208):
 * all born pending approval, edited only while pending (D-10), deleted only while
 * pending or rejected (D-11). Amounts are always computed here (D-12).
 */
class PurchaseRequestLineSynchronizer
{
    /** Line states a line may be deleted from (D-11). */
    private const array DELETABLE_STATUSES = [PurchaseRequestLineStatus::PendingApproval, PurchaseRequestLineStatus::Rejected];

    private const int QUANTITY_SCALE = 3;

    public function __construct(
        private readonly LineAmountCalculator $amounts,
        private readonly PurchaseRequestCapabilityResolver $capabilities,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createAll(PurchaseRequest $purchaseRequest, array $lines): void
    {
        $rates = $this->vatRates($lines);

        foreach ($lines as $index => $line) {
            $this->createLine($purchaseRequest, $line, $index + 1, $rates);
        }

        $this->refreshTotals($purchaseRequest);
    }

    /**
     * Align the stored lines of the (already locked) request with the payload.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function sync(PurchaseRequest $locked, User $actor, array $lines): void
    {
        // Step 1: check the payload against the stored lines before writing anything
        $existing = $locked->lines()->lockForUpdate()->get()->keyBy('id');
        $rates = $this->vatRates($lines);
        $this->assertLinesAreValid($locked, $actor, $existing, $lines);
        $removed = $this->removedLines($existing, $lines);
        $this->assertRemovalAllowed($removed, $actor);

        // Step 2: delete / update / create
        $removed->each(fn (PurchaseRequestLine $line) => $line->delete());

        foreach ($lines as $index => $line) {
            $stored = $line['id'] === null ? null : $existing->get($line['id']);
            $stored === null
                ? $this->createLine($locked, $line, $index + 1, $rates)
                : $this->updateLine($stored, $line, $index + 1, $rates);
        }

        // Step 3: totals from the lines
        $this->refreshTotals($locked);
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

        // An approved line may also take a new supplier (already authorized by assertLinesAreValid).
        $stored->fill(['position' => $position] + ($stored->status === PurchaseRequestLineStatus::Approved ? ['supplier_id' => $line['supplier_id']] : []))->save();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function lineContent(array $line, int $position): array
    {
        $amounts = $this->amounts->line($line['quantity'], $line['unit_price'], null, self::QUANTITY_SCALE);

        return [
            'position' => $position,
            'product_id' => $line['product_id'],
            'description' => $line['description'],
            'supplier_id' => $line['supplier_id'],
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
        $amounts = $this->amounts->line($line['quantity'], $line['unit_price'], $rates[$line['vat_rate_id']] ?? null, self::QUANTITY_SCALE);

        return [
            'taxable_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
        ];
    }

    private function refreshTotals(PurchaseRequest $purchaseRequest): void
    {
        $totals = $this->amounts->sum(
            $purchaseRequest->lines()->get(['taxable_amount', 'vat_amount'])
                ->map(fn (PurchaseRequestLine $line): array => ['net' => $line->taxable_amount, 'vat' => $line->vat_amount]),
        );

        $purchaseRequest->forceFill(['taxable_total' => $totals['net'], 'vat_total' => $totals['vat'], 'grand_total' => $totals['total']])->save();
    }

    /**
     * Existing lines must belong to this request (D-10) and, unless pending,
     * keep their values; the supplier alone may change where the actor may
     * update it (spec 0209, D-3).
     *
     * @param  Collection<int, PurchaseRequestLine>  $existing  keyed by id
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertLinesAreValid(PurchaseRequest $request, User $actor, Collection $existing, array $lines): void
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

            if ($stored->status !== PurchaseRequestLineStatus::PendingApproval
                && ($this->contentChanged($stored, $line)
                    || ($this->supplierChanged($stored, $line) && ! $this->capabilities->canUpdateSupplier($actor, $request, $stored, true)))) {
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

        foreach (array_diff(array_keys($submitted), ['position', 'supplier_id']) as $key) {
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
     * @param  array<string, mixed>  $line
     */
    private function supplierChanged(PurchaseRequestLine $stored, array $line): bool
    {
        return (string) $line['supplier_id'] !== (string) $stored->supplier_id;
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

        Gate::forUser($actor)->authorize('purchase-requests.deleteLine');

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
