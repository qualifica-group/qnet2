<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

/**
 * Quantities of one purchase order line (spec 0254, D-2, D-3). Every figure is
 * a bcmath string with 3 decimals; the rules live here so guards, draft
 * builder and aggregators cannot drift apart.
 */
final readonly class PurchaseOrderLineBalance
{
    private const int SCALE = 3;

    private const string ZERO = '0.000';

    /**
     * @param  string  $ordered  ordered quantity
     * @param  string  $received  sum of the receipt lines
     * @param  bool  $residualClosed  the missing goods will not arrive
     * @param  string  $invoicedTotal  proforma + invoice lines referencing the line
     * @param  string  $invoicedInvoice  invoice lines only
     */
    public function __construct(
        public int $lineId,
        public string $ordered,
        public string $received,
        public bool $residualClosed,
        public string $invoicedTotal,
        public string $invoicedInvoice,
    ) {}

    /** Billable ceiling: the received quantity once the residual is closed, the ordered one otherwise. */
    public function ceiling(): string
    {
        return $this->residualClosed ? $this->received : $this->ordered;
    }

    public function isComplete(): bool
    {
        return $this->residualClosed || bccomp($this->received, $this->ordered, self::SCALE) >= 0;
    }

    /** Quantity still to receive: zero for a complete line. */
    public function residual(): string
    {
        return $this->isComplete() ? self::ZERO : bcsub($this->ordered, $this->received, self::SCALE);
    }

    /** Quantity a payable document (proforma or invoice alike, D-2) can still bill on the line. */
    public function available(): string
    {
        $room = bcsub($this->ceiling(), $this->invoicedTotal, self::SCALE);

        return bccomp($room, self::ZERO, self::SCALE) > 0 ? $room : self::ZERO;
    }

    /** Quantity an invoice billing the line still has to receive (D-12): invoiced as invoice minus received, within the residual. */
    public function toReceive(): string
    {
        $gap = bcsub($this->invoicedInvoice, $this->received, self::SCALE);
        $residual = bcsub($this->ordered, $this->received, self::SCALE);

        if (bccomp($gap, self::ZERO, self::SCALE) <= 0 || bccomp($residual, self::ZERO, self::SCALE) <= 0) {
            return self::ZERO;
        }

        return bccomp($gap, $residual, self::SCALE) <= 0 ? $gap : $residual;
    }

    /** @return 'open'|'partial'|'complete' */
    public function fulfillment(): string
    {
        if ($this->isComplete()) {
            return 'complete';
        }

        return bccomp($this->received, self::ZERO, self::SCALE) > 0 ? 'partial' : 'open';
    }
}
