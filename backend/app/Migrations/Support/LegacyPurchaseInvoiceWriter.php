<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrderLine;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\PurchaseInvoices\PurchaseInvoiceAmountCalculator;
use App\Services\PurchaseInvoices\PurchaseInvoiceInstallmentPlanner;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The lines and the schedule of a legacy supplier invoice (spec 0298). The
 * lines bill the migrated order lines of the invoiced orders; the amounts are
 * qnet-2's (PurchaseInvoiceAmountCalculator). The schedule is the legacy one,
 * its last installment absorbing any difference from the qnet-2 total; with
 * none, the payment method plans it (D-6). A paid legacy invoice has every
 * installment paid in full (D-3).
 */
final class LegacyPurchaseInvoiceWriter
{
    private const int DESCRIPTION_MAX = 500;

    public function __construct(
        private readonly PurchaseInvoiceAmountCalculator $amounts,
        private readonly PurchaseInvoiceInstallmentPlanner $planner,
        private readonly LegacyCostTypeLookup $costTypes,
    ) {}

    /**
     * The payload lines with their qnet-2 ids, then their amounts.
     *
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, int>  $orderIds  the migrated orders the invoice bills
     * @param  array<int, string>  $warnings
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     *
     * @throws RuntimeException when a line is not on the invoiced orders or its cost type is unknown
     */
    public function computeLines(array $legacyLines, array $orderIds, array &$warnings): array
    {
        $lines = [];

        foreach ($legacyLines as $legacyLine) {
            $legacyLine = (array) $legacyLine;
            $legacyId = $legacyLine['purchase_order_line_id'] ?? '?';
            $orderLine = PurchaseOrderLine::query()->where('old_id', $legacyId)->whereIn('purchase_order_id', $orderIds)->first(['id'])
                ?? throw new RuntimeException("Order line (legacy id {$legacyId}) not migrated on the invoiced orders; the invoice is not imported.");

            $lines[] = [
                'purchase_order_line_id' => $orderLine->id,
                'description' => $this->description($legacyLine['description'] ?? null, $legacyId, $warnings),
                'quantity' => $legacyLine['quantity'] ?? 0,
                'unit_price' => $legacyLine['unit_price'] ?? 0,
                'vat_rate_id' => $this->optionalOldId(VatRate::class, $legacyLine['vat_rate_id'] ?? null, "Line {$legacyId}: VAT rate", $warnings),
                'cost_type_id' => $this->costTypes->idFor($legacyLine['cost_type_name'] ?? null, "Line {$legacyId}"),
                'work_order_id' => $this->optionalOldId(WorkOrder::class, $legacyLine['work_order_id'] ?? null, "Line {$legacyId}: work order", $warnings),
            ];
        }

        return $this->amounts->compute($lines);
    }

    /**
     * @param  array<int, mixed>  $legacyInstallments
     * @param  array<int, string>  $warnings
     */
    public function writeSchedule(PurchaseInvoice $invoice, array $legacyInstallments, ?string $paidAt, array &$warnings): void
    {
        // Step 1: the legacy schedule, or the plan of the payment method when there is none.
        $installments = $legacyInstallments === []
            ? $this->planned($invoice, $warnings)
            : $this->legacy($legacyInstallments, (string) $invoice->total_amount, $warnings);

        // Step 2: a paid legacy invoice has every installment paid in full (D-3).
        if ($paidAt !== null) {
            $installments = array_map(fn (array $installment): array => $installment + ['paid_amount' => $installment['amount'], 'paid_at' => $paidAt], $installments);
            $warnings[] = "Paid in the legacy: every installment is recorded as paid on {$paidAt}.";
        }

        $invoice->installments()->createMany($installments);
    }

    /**
     * @param  array<int, mixed>  $legacyInstallments
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function legacy(array $legacyInstallments, string $total, array &$warnings): array
    {
        $installments = [];
        $sum = '0.00';

        foreach (array_values($legacyInstallments) as $index => $legacyInstallment) {
            $legacyInstallment = (array) $legacyInstallment;
            $amount = number_format((float) ($legacyInstallment['amount'] ?? 0), 2, '.', '');
            $sum = bcadd($sum, $amount, 2);
            $installments[] = [
                'sequence' => $index + 1,
                'due_date' => (string) ($legacyInstallment['due_date'] ?? ''),
                'amount' => $amount,
                'payment_method_code' => $legacyInstallment['payment_method_code'] ?? null,
            ];
        }

        $difference = bcsub($total, $sum, 2);

        if (bccomp($difference, '0', 2) !== 0) {
            $last = array_key_last($installments);
            $installments[$last]['amount'] = bcadd($installments[$last]['amount'], $difference, 2);
            $warnings[] = "The legacy installments sum to {$sum}, the invoice total is {$total}: the last installment absorbs the difference.";
        }

        return $installments;
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function planned(PurchaseInvoice $invoice, array &$warnings): array
    {
        $warnings[] = 'No installment in the legacy: the schedule comes from the payment method.';

        return $this->planner->plan(
            CarbonImmutable::parse($invoice->document_date),
            PaymentMethod::query()->findOrFail($invoice->payment_method_id),
            (string) $invoice->net_amount,
            (string) $invoice->vat_amount,
            (string) $invoice->total_amount,
        );
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
     * @param  class-string<VatRate|WorkOrder>  $targetClass
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
}
