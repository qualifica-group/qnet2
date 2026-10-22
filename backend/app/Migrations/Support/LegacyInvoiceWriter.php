<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Migrations\Sources\ProductsSource;
use App\Migrations\Support\Concerns\BuildsLegacyDocumentLines;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Services\Invoices\InvoiceAmountCalculator;
use App\Services\Invoices\InvoiceInstallmentPlanner;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The lines and the schedule of a legacy active document (spec 0301). The
 * amounts are qnet-2's (InvoiceAmountCalculator, VAT per rate); a line needs
 * its VAT rate (NOT NULL in qnet-2). The schedule is the legacy one with its
 * collections (D-1), the last installment absorbing any difference from the
 * total; with none, the payment method plans it (D-6).
 */
final class LegacyInvoiceWriter
{
    use BuildsLegacyDocumentLines;

    public function __construct(
        private readonly InvoiceAmountCalculator $amounts,
        private readonly InvoiceInstallmentPlanner $planner,
    ) {}

    /**
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, string>  $warnings
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     *
     * @throws RuntimeException when a line has no VAT rate migrated
     */
    public function computeLines(array $legacyLines, array &$warnings): array
    {
        $lines = [];

        foreach (array_values($legacyLines) as $index => $legacyLine) {
            $legacyLine = (array) $legacyLine;
            $label = 'Line '.($index + 1);
            $lines[] = [
                'product_id' => $this->productId($legacyLine['product_id'] ?? null, $label, $warnings),
                'quote_line_id' => $this->optionalOldId(QuoteLine::class, $legacyLine['quote_line_id'] ?? null, "{$label}: quote line", $warnings),
                'description' => $this->lineDescription($legacyLine['description'] ?? null, $label, $warnings),
                'quantity' => $legacyLine['quantity'] ?? 0,
                'unit_price' => $legacyLine['unit_price'] ?? 0,
                'vat_rate_id' => $this->vatRateId($legacyLine['vat_rate_id'] ?? null, $label),
            ];
        }

        if ($lines === []) {
            throw new RuntimeException('No line; the document is not imported.');
        }

        return $this->amounts->compute($lines, $this->amounts->vatRates(array_column($lines, 'vat_rate_id')));
    }

    /**
     * @param  array<int, mixed>  $legacyInstallments
     * @param  array<int, string>  $warnings
     */
    public function writeSchedule(Invoice $invoice, array $legacyInstallments, array &$warnings): void
    {
        $installments = $legacyInstallments === []
            ? $this->planned($invoice, $warnings)
            : $this->legacy($legacyInstallments, (string) $invoice->total_amount, $warnings);

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

        foreach (array_values($legacyInstallments) as $index => $legacyInstallment) {
            $legacyInstallment = (array) $legacyInstallment;
            $collected = $legacyInstallment['collected_amount'] ?? null;
            $installments[] = [
                'sequence' => $index + 1,
                'due_date' => (string) ($legacyInstallment['due_date'] ?? ''),
                'amount' => $this->money($legacyInstallment['amount'] ?? 0),
                'payment_method_code' => $legacyInstallment['payment_method_code'] ?? null,
                'collected_amount' => $collected === null ? null : $this->money($collected),
                'collected_at' => $collected === null ? null : ($legacyInstallment['collected_at'] ?? null),
            ];
        }

        return $this->absorbDifference($installments, $total, 'document', $warnings);
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function planned(Invoice $invoice, array &$warnings): array
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
    private function productId(mixed $legacyId, string $label, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $id = Product::query()->where('old_source', ProductsSource::OLD_SOURCE)->where('old_id', $legacyId)->value('id');

        if ($id === null) {
            $warnings[] = "{$label}: product (legacy id {$legacyId}) not migrated, left empty.";
        }

        return $id;
    }

    /**
     * @throws RuntimeException when the VAT rate is missing or not migrated
     */
    private function vatRateId(mixed $legacyId, string $label): int
    {
        if ($legacyId === null || $legacyId === '') {
            throw new RuntimeException("{$label}: VAT rate is required; the document is not imported.");
        }

        return VatRate::query()->where('old_id', $legacyId)->value('id')
            ?? throw new RuntimeException("{$label}: VAT rate (legacy id {$legacyId}) not migrated; the document is not imported.");
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
