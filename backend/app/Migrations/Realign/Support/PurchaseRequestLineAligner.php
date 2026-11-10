<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Enums\PurchaseRequestLineStatus;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Support\LegacyPurchaseRequestLineWriter;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Support\Amounts\LineAmountCalculator;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Aligns the lines of an RDA by `old_id` (spec 0375 D-11): a line present is
 * updated, a new one is created with the import's writer, one the legacy no
 * longer sends stays and is reported.
 */
final class PurchaseRequestLineAligner
{
    public function __construct(
        private readonly LegacyPurchaseRequestLineWriter $writer,
        private readonly LineAmountCalculator $amounts,
        private readonly PurchaseLegacyReferences $references,
    ) {}

    /**
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, string>  $warnings
     * @return bool whether any line changed; the request totals are then updated in memory
     */
    public function align(PurchaseRequest $purchaseRequest, array $legacyLines, array &$warnings, LegacyRealignNotes $notes): bool
    {
        // Step 1: split the legacy lines into existing and new.
        $existing = $purchaseRequest->lines()->whereNotNull('old_id')->get()->keyBy(fn (PurchaseRequestLine $line): string => (string) $line->old_id);
        $newLines = [];
        $changed = false;

        foreach ($legacyLines as $legacyLine) {
            $legacyLine = (array) $legacyLine;
            $line = $existing->pull((string) ($legacyLine['id'] ?? ''));

            if ($line === null) {
                $newLines[] = $legacyLine;
            } else {
                $changed = $this->updateLine($line, $legacyLine, $warnings) || $changed;
            }
        }

        // Step 2: what the legacy no longer sends stays (D-9).
        foreach ($existing as $line) {
            $notes->warn("Line {$line->old_id} is no longer in the legacy; kept.");
        }

        if ($newLines === [] && ! $changed) {
            return false;
        }

        // Step 3: new lines and the totals, through the import's writer.
        $this->writer->write($purchaseRequest, $newLines, $warnings);

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @param  array<int, string>  $warnings
     */
    private function updateLine(PurchaseRequestLine $line, array $legacyLine, array &$warnings): bool
    {
        $lineId = $line->old_id;
        $status = PurchaseRequestLineStatus::tryFrom((string) ($legacyLine['status'] ?? ''))
            ?? throw new RuntimeException("Line {$lineId}: status '".($legacyLine['status'] ?? '')."' is not valid.");
        $vatRateId = $this->vatRateId($line, $legacyLine, $warnings);
        $rate = $vatRateId === null ? null : (string) VatRate::query()->whereKey($vatRateId)->value('rate');
        $amounts = $this->amounts->line($legacyLine['quantity'] ?? 0, $legacyLine['unit_price'] ?? 0, $rate, LegacyPurchaseRequestLineWriter::QUANTITY_SCALE);

        $line->forceFill([
            'position' => (int) ($legacyLine['position'] ?? 0),
            'product_id' => $this->references->optionalOrCurrent(Product::class, $legacyLine['product_id'] ?? null, $line->product_id, "Line {$lineId}: product", $warnings),
            'description' => $this->writer->description($legacyLine['description'] ?? null, $lineId, $warnings),
            'supplier_id' => $this->references->optionalOrCurrent(Registry::class, $legacyLine['supplier_id'] ?? null, $line->supplier_id, "Line {$lineId}: supplier", $warnings),
            'reason' => $this->references->text($legacyLine['reason'] ?? null),
            'unit_of_measure_id' => $this->writer->unitId($legacyLine['unit_of_measure_code'] ?? null, $lineId, $warnings) ?? $line->unit_of_measure_id,
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $vatRateId,
            'taxable_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
            'status' => $this->statusToWrite($line, $status, $warnings),
            'approved_by' => $this->references->optionalOrCurrent(User::class, $legacyLine['approved_by_user_id'] ?? null, $line->approved_by, "Line {$lineId}: approver", $warnings),
            'approved_at' => $this->references->text($legacyLine['approved_at'] ?? null),
        ]);

        $changed = $line->isDirty();

        if ($changed) {
            $updatedAt = $this->references->timestamp($legacyLine['updated_at'] ?? null);
            $line->forceFill($updatedAt !== null ? ['updated_at' => $updatedAt] : [])->saveQuietly();
        }

        return $this->appendMissingLogs($line, (array) ($legacyLine['status_logs'] ?? []), $warnings) || $changed;
    }

    /**
     * "Received" comes from the receipts of the order on qnet-2, a state the legacy line
     * never reaches: an earlier legacy status does not undo it (D-11).
     *
     * @param  array<int, string>  $warnings
     */
    private function statusToWrite(PurchaseRequestLine $line, PurchaseRequestLineStatus $status, array &$warnings): PurchaseRequestLineStatus
    {
        $goesBack = in_array($status, [PurchaseRequestLineStatus::PendingApproval, PurchaseRequestLineStatus::Approved, PurchaseRequestLineStatus::Ordered], true);

        if ($line->status === PurchaseRequestLineStatus::Received && $goesBack) {
            $warnings[] = "Line {$line->old_id}: status '{$status->value}' in the legacy, kept as received on qnet-2.";

            return $line->status;
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @param  array<int, string>  $warnings
     */
    private function vatRateId(PurchaseRequestLine $line, array $legacyLine, array &$warnings): ?int
    {
        $legacyId = $legacyLine['vat_rate_id'] ?? null;

        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        return $this->writer->vatRate($legacyId, $line->old_id, $warnings)['id'] ?? $line->vat_rate_id;
    }

    /**
     * The status history has no legacy anchor: a change is new when no row has
     * the same target status at the same moment.
     *
     * @param  array<int, mixed>  $legacyLogs
     * @param  array<int, string>  $warnings
     */
    private function appendMissingLogs(PurchaseRequestLine $line, array $legacyLogs, array &$warnings): bool
    {
        $known = PurchaseRequestLineStatusLog::query()->where('purchase_request_line_id', $line->id)->get()
            ->map(fn (PurchaseRequestLineStatusLog $log): string => $this->logKey($log->to_status->value, CarbonImmutable::parse($log->created_at)))
            ->all();

        $missing = array_values(array_filter($legacyLogs, function (mixed $legacyLog) use ($line, $known): bool {
            $legacyLog = (array) $legacyLog;
            $createdAt = $this->references->timestamp($legacyLog['created_at'] ?? null) ?? CarbonImmutable::parse($line->created_at);

            return ! in_array($this->logKey((string) ($legacyLog['to_status'] ?? ''), $createdAt), $known, true);
        }));

        if ($missing === []) {
            return false;
        }

        $this->writer->writeLogs($line, $missing, $warnings);

        return true;
    }

    private function logKey(string $toStatus, CarbonImmutable $createdAt): string
    {
        return $toStatus.'|'.$createdAt->format('Y-m-d H:i:s');
    }
}
