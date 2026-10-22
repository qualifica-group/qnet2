<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\PurchaseRequestLineStatusLog;
use App\Models\Registry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VatRate;
use App\Support\Amounts\LineAmountCalculator;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Writes the lines of a freshly imported legacy RDA (spec 0294) with their
 * status history, then the request totals. The amounts are qnet-2's own
 * (LineAmountCalculator, VAT per line as PurchaseRequestLineSynchronizer
 * does), never the legacy ones. Lines and logs are written quietly: the caller
 * runs without activity log and nothing is notified.
 *
 * A line status outside the enum fails the request; a missing product,
 * supplier, VAT rate, unit or approver, and an unusable log, are dropped with
 * a warning (D-6).
 */
final class LegacyPurchaseRequestLineWriter
{
    /** Scale of `purchase_request_lines.quantity`, the same the synchronizer rounds to. */
    private const int QUANTITY_SCALE = 3;

    private const int DESCRIPTION_MAX = 500;

    /** @var array<string, int|null> */
    private array $unitIds = [];

    /** @var array<int, array{id: int, rate: string}|null> */
    private array $vatRates = [];

    public function __construct(private readonly LineAmountCalculator $amounts) {}

    /**
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, string>  $warnings
     */
    public function write(PurchaseRequest $purchaseRequest, array $legacyLines, array &$warnings): void
    {
        foreach ($legacyLines as $legacyLine) {
            $legacyLine = (array) $legacyLine;

            // Step 1: the line with qnet-2 amounts.
            $line = $this->createLine($purchaseRequest, $legacyLine, $warnings);

            // Step 2: its status history.
            $this->writeLogs($line, (array) ($legacyLine['status_logs'] ?? []), $warnings);
        }

        // Step 3: the request totals, summed from the lines just written.
        $totals = $this->amounts->sum(
            $purchaseRequest->lines()->get(['taxable_amount', 'vat_amount'])
                ->map(fn (PurchaseRequestLine $line): array => ['net' => $line->taxable_amount, 'vat' => $line->vat_amount]),
        );
        $purchaseRequest->forceFill(['taxable_total' => $totals['net'], 'vat_total' => $totals['vat'], 'grand_total' => $totals['total']]);
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @param  array<int, string>  $warnings
     */
    private function createLine(PurchaseRequest $purchaseRequest, array $legacyLine, array &$warnings): PurchaseRequestLine
    {
        $lineId = $legacyLine['id'] ?? '?';
        $status = PurchaseRequestLineStatus::tryFrom((string) ($legacyLine['status'] ?? ''))
            ?? throw new RuntimeException("Line {$lineId}: status '".($legacyLine['status'] ?? '')."' is not valid.");
        $vatRate = $this->vatRate($legacyLine['vat_rate_id'] ?? null, $lineId, $warnings);
        $amounts = $this->amounts->line($legacyLine['quantity'] ?? 0, $legacyLine['unit_price'] ?? 0, $vatRate['rate'] ?? null, self::QUANTITY_SCALE);

        $line = new PurchaseRequestLine([
            'position' => (int) ($legacyLine['position'] ?? 0),
            'product_id' => $this->optionalOldId(Product::class, $legacyLine['product_id'] ?? null, "Line {$lineId}: product", $warnings),
            'description' => $this->description($legacyLine['description'] ?? null, $lineId, $warnings),
            'supplier_id' => $this->optionalOldId(Registry::class, $legacyLine['supplier_id'] ?? null, "Line {$lineId}: supplier", $warnings),
            'reason' => $this->blankToNull($legacyLine['reason'] ?? null),
            'unit_of_measure_id' => $this->unitId($legacyLine['unit_of_measure_code'] ?? null, $lineId, $warnings),
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $vatRate['id'] ?? null,
        ]);
        $line->forceFill([
            'old_id' => $legacyLine['id'] ?? null,
            'purchase_request_id' => $purchaseRequest->id,
            'taxable_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
            'status' => $status,
            'approved_by' => $this->optionalOldId(User::class, $legacyLine['approved_by_user_id'] ?? null, "Line {$lineId}: approver", $warnings),
            'approved_at' => $this->blankToNull($legacyLine['approved_at'] ?? null),
            ...$this->timestamps($legacyLine),
        ])->saveQuietly();

        return $line;
    }

    /**
     * @param  array<int, mixed>  $legacyLogs
     * @param  array<int, string>  $warnings
     */
    private function writeLogs(PurchaseRequestLine $line, array $legacyLogs, array &$warnings): void
    {
        foreach ($legacyLogs as $legacyLog) {
            $legacyLog = (array) $legacyLog;
            $toStatus = PurchaseRequestLineStatus::tryFrom((string) ($legacyLog['to_status'] ?? ''));
            $userId = $this->userId($legacyLog['user_id'] ?? null);

            if ($toStatus === null || $userId === null) {
                $warnings[] = "Line {$line->old_id}: status change skipped (user not migrated or status not valid).";

                continue;
            }

            $bulkGroupId = $this->blankToNull($legacyLog['bulk_group_id'] ?? null);
            $log = new PurchaseRequestLineStatusLog([
                'purchase_request_line_id' => $line->id,
                'user_id' => $userId,
                'from_status' => PurchaseRequestLineStatus::tryFrom((string) ($legacyLog['from_status'] ?? '')),
                'to_status' => $toStatus,
                'reason' => $this->blankToNull($legacyLog['reason'] ?? null),
                'bulk_group_id' => $bulkGroupId !== null && Str::isUuid($bulkGroupId) ? $bulkGroupId : null,
            ]);
            $log->created_at = $this->blankToNull($legacyLog['created_at'] ?? null) ?? $line->created_at;
            $log->saveQuietly();
        }
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function description(mixed $legacyDescription, int|string $lineId, array &$warnings): string
    {
        $description = trim((string) ($legacyDescription ?? ''));

        if ($description === '') {
            throw new RuntimeException("Line {$lineId}: description is required.");
        }

        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $warnings[] = "Line {$lineId}: description truncated to ".self::DESCRIPTION_MAX.' characters.';

            return mb_substr($description, 0, self::DESCRIPTION_MAX);
        }

        return $description;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function unitId(mixed $code, int|string $lineId, array &$warnings): ?int
    {
        $code = $this->blankToNull($code);

        if ($code === null) {
            return null;
        }

        $this->unitIds[$code] ??= UnitOfMeasure::query()->where('code', $code)->value('id');

        if ($this->unitIds[$code] === null) {
            $warnings[] = "Line {$lineId}: unit of measure '{$code}' not found, left empty.";
        }

        return $this->unitIds[$code];
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{id: int, rate: string}|null
     */
    private function vatRate(mixed $legacyId, int|string $lineId, array &$warnings): ?array
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $key = (int) $legacyId;

        if (! array_key_exists($key, $this->vatRates)) {
            $vatRate = VatRate::query()->where('old_id', $key)->first(['id', 'rate']);
            $this->vatRates[$key] = $vatRate === null ? null : ['id' => $vatRate->id, 'rate' => (string) $vatRate->rate];
        }

        if ($this->vatRates[$key] === null) {
            $warnings[] = "Line {$lineId}: VAT rate (legacy id {$key}) not migrated, left empty.";
        }

        return $this->vatRates[$key];
    }

    /**
     * @param  class-string<Product|Registry|User>  $targetClass
     * @param  array<int, string>  $warnings
     */
    private function optionalOldId(string $targetClass, mixed $legacyId, string $label, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $id = $targetClass::query()->where('old_id', $legacyId)->value('id');

        if ($id === null) {
            $warnings[] = "{$label} (legacy id {$legacyId}) not migrated, left empty.";
        }

        return $id;
    }

    private function userId(mixed $legacyId): ?int
    {
        return $legacyId === null || $legacyId === '' ? null : User::query()->where('old_id', $legacyId)->value('id');
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @return array<string, string>
     */
    private function timestamps(array $legacyLine): array
    {
        $createdAt = $this->blankToNull($legacyLine['created_at'] ?? null);
        $updatedAt = $this->blankToNull($legacyLine['updated_at'] ?? null) ?? $createdAt;

        return array_filter(['created_at' => $createdAt, 'updated_at' => $updatedAt], fn (?string $value): bool => $value !== null);
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
