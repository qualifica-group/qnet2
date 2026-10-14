<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Services\PurchaseOrders\PurchaseOrderLineBalance;
use App\Services\PurchaseOrders\PurchaseOrderLineBillingLedger;
use App\Support\Amounts\LineAmountCalculator;

/**
 * Quantity rule of the payable documents that bill purchase order lines (spec
 * 0254, D-2): what a document bills on a line cannot exceed what the ledger
 * still lets it bill. Always called inside the caller's transaction: the order
 * lines and the document lines are read FOR UPDATE (lock order: orders, order
 * lines, document lines), so the verdict still holds at commit.
 */
final class PurchaseInvoiceQuantityGuard
{
    private const int SCALE = 3;

    private const string ZERO = '0.000';

    public function __construct(
        private readonly PurchaseOrderLineBillingLedger $ledger,
        private readonly LineAmountCalculator $engine,
    ) {}

    /**
     * Errors of the payload lines, keyed `lines.N.quantity` on the first row of
     * each offending order line (the same order line may sit on several rows:
     * the rule applies to their sum). The caller already holds the order locks.
     *
     * @param  array<int, array<string, mixed>>  $lines  payload lines keyed by their index
     * @return array<string, array<int, string>>
     */
    public function lineErrors(array $lines, ?int $ignoreInvoiceId): array
    {
        // Step 1: requested quantity per order line, first row remembered
        $requested = [];
        $firstRow = [];

        foreach ($lines as $index => $line) {
            $orderLineId = (int) ($line['purchase_order_line_id'] ?? 0);

            if ($orderLineId === 0) {
                continue;
            }

            $requested[$orderLineId] = bcadd($requested[$orderLineId] ?? self::ZERO, $this->quantity($line['quantity']), self::SCALE);
            $firstRow[$orderLineId] ??= $index;
        }

        // Step 2: balances under lock, then the verdict per order line
        $balances = $this->ledger->forLines(array_keys($requested), $ignoreInvoiceId, true);
        $errors = [];

        foreach ($requested as $orderLineId => $quantity) {
            $message = $this->overage($balances->get($orderLineId), $quantity);

            if ($message !== null) {
                $errors["lines.{$firstRow[$orderLineId]}.quantity"] = [$message];
            }
        }

        return $errors;
    }

    private function overage(?PurchaseOrderLineBalance $balance, string $requested): ?string
    {
        if ($balance === null) {
            return null;
        }

        $available = $balance->available();

        return bccomp($requested, $available, self::SCALE) > 0
            ? __('purchase_invoices.line_over_available', ['available' => $this->display($available)])
            : null;
    }

    /** Quantity as the document stores it (engine rounding), bcmath scale 3. */
    private function quantity(mixed $value): string
    {
        return $this->engine->line($value, '0', null, self::SCALE)['quantity'];
    }

    private function display(string $quantity): string
    {
        return rtrim(rtrim($quantity, '0'), '.') ?: '0';
    }
}
