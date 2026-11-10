<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\Commissions\CommissionCalculationInput;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use App\Services\Commissions\QuoteLineCommissionBaseResolver;
use Illuminate\Support\Collection;

/**
 * Builds the "Dati contrattuali" payload of a commessa (spec 0201). Every
 * figure comes from data already persisted on the offer (D-7), nothing is
 * recalculated into storage; amounts are summed as integer cents.
 *
 * Per line: "effective revenue" is the Fornitore commission for a line whose
 * frozen Supplier commission direction is RECEIVED and the net amount
 * otherwise (spec 0202, D-8/D-11 — the typology code is never read), next to
 * the spec 0145 "net of commissions". When a line carries several Fornitore commissions,
 * `amount` is their sum while `commission_type`/`value`/`base_amount` describe
 * the first one. The commission details are hidden (null) unless the actor sees
 * the offer's `commissions` and `commission_value` fields (D-10), the same rule
 * as QuoteResource::summarizeTotals.
 *
 * Without `WorkOrderPolicy::viewCosts` on the commessa no figure leaves the
 * server (spec 0380): every amount and quantity of a line is null, its warnings
 * are empty, `totals` is null and `figures_visible` is false. Product and
 * payment stay.
 */
final class WorkOrderContractDataBuilder
{
    public const string WARNING_MISSING_SUPPLIER_COMMISSION = 'missing_supplier_commission';

    public const string WARNING_STALE_COMMISSION_BASE = 'stale_commission_base';

    /** The keys of a line that carry a figure, hidden without viewCosts (spec 0380). */
    private const array FIGURE_KEYS = ['quantity', 'unit_price', 'net_amount', 'supplier_commission', 'commissions_amount', 'net_of_commissions', 'effective_revenue'];

    /** Relations read per line, eager loaded to avoid N+1. */
    private const array LINE_RELATIONS = ['product.productTypology', 'commissions', 'quote.opportunity.registry'];

    public function __construct(
        private readonly QuoteCommissionPayloadRedactor $redactor,
        private readonly QuoteLineCommissionBaseResolver $baseResolver,
        private readonly CommissionCalculator $calculator,
        private readonly LineEffectiveRevenue $effectiveRevenue,
    ) {}

    /**
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, mixed>|null, commissions_visible: bool, figures_visible: bool}
     */
    public function build(WorkOrder $workOrder, User $actor): array
    {
        // Step 1: load the commessa's lines and decide the figure and commission visibility
        $figuresVisible = $this->figuresVisible($workOrder, $actor);
        $visibleByQuote = $this->visibilityByQuote($workOrder, $actor);
        $visible = $figuresVisible && ! in_array(false, $visibleByQuote, true);
        $lines = $workOrder->quoteLines()->with(self::LINE_RELATIONS)->orderBy('quote_lines.id')->get();

        // Step 2: figures per line
        $rows = $this->rows($lines, $visibleByQuote, $this->paymentsByLine($workOrder));
        $payloads = array_column($rows, 'payload');

        // Step 3: without viewCosts no figure leaves the server (spec 0380)
        return [
            'lines' => $figuresVisible ? $payloads : array_map($this->withoutFigures(...), $payloads),
            'totals' => $figuresVisible ? $this->totals($rows, $visible) : null,
            'commissions_visible' => $visible,
            'figures_visible' => $figuresVisible,
        ];
    }

    /**
     * The payload of one line, the shape of `lines[]` (PATCH response).
     *
     * @return array<string, mixed>
     */
    public function buildLine(WorkOrder $workOrder, QuoteLine $line, User $actor): array
    {
        $line = QuoteLine::query()->with(self::LINE_RELATIONS)->findOrFail($line->id);
        $payload = $this->rows(collect([$line]), $this->visibilityByQuote($workOrder, $actor), $this->paymentsByLine($workOrder))[0]['payload'];

        return $this->figuresVisible($workOrder, $actor) ? $payload : $this->withoutFigures($payload);
    }

    /** Spec 0380: quantities, prices and amounts follow the "Costi" permission of the commessa. */
    private function figuresVisible(WorkOrder $workOrder, User $actor): bool
    {
        return $actor->can('viewCosts', $workOrder);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutFigures(array $payload): array
    {
        return [...$payload, ...array_fill_keys(self::FIGURE_KEYS, null), 'warnings' => []];
    }

    /**
     * The payment rows of THIS commessa keyed by quote line (spec 0358, D-6):
     * the same line can be in several commesse, each with its own payment.
     *
     * @return Collection<int, WorkOrderLinePayment>
     */
    private function paymentsByLine(WorkOrder $workOrder): Collection
    {
        return $workOrder->linePayments()->with('status')->get()->keyBy('quote_line_id');
    }

    /**
     * Commission visibility per source offer (spec 0267, D-10): each line is
     * judged by the field permissions of its OWN offer.
     *
     * @return array<int, bool>
     */
    private function visibilityByQuote(WorkOrder $workOrder, User $actor): array
    {
        $quotes = $workOrder->isMultiQuote()
            ? Quote::query()->whereIn('id', $workOrder->sourceQuoteIds())->get()
            : collect([$workOrder->loadMissing('quote')->quote]);

        $visibility = [];

        foreach ($quotes as $quote) {
            $permissions = $this->redactor->permissions($actor, $quote);
            $visibility[$quote->id] = $permissions['commissions']->visible && $permissions['commission_value']->visible;
        }

        return $visibility;
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     * @param  array<int, bool>  $visibleByQuote
     * @param  Collection<int, WorkOrderLinePayment>  $payments
     * @return array<int, array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}>
     */
    private function rows(Collection $lines, array $visibleByQuote, Collection $payments): array
    {
        $bases = $this->baseResolver->resolveForLines($lines);

        return $lines->map(fn (QuoteLine $line): array => $this->row($line, $bases[$line->id], $visibleByQuote[$line->quote_id] ?? false, $payments->get($line->id)))->values()->all();
    }

    /**
     * @return array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}
     */
    private function row(QuoteLine $line, string $base, bool $visible, ?WorkOrderLinePayment $payment): array
    {
        $typology = $line->product?->productTypology;
        $direction = $line->supplier_commission_direction;
        $received = $direction === SupplierCommissionDirection::Received;
        $supplier = $line->commissions->filter(fn (QuoteLineCommission $c): bool => $c->recipient_role === CommissionRecipientRole::Supplier)->sortBy('id')->values();

        $net = $this->cents($line->net_amount);
        $supplierCents = $this->effectiveRevenue->supplierCommissionCents($line);
        $commissions = $line->commissions->sum(fn (QuoteLineCommission $c): int => $this->cents($c->calculated_amount));
        $revenue = $this->effectiveRevenue->cents($line);
        $stale = $supplier->contains(fn (QuoteLineCommission $c): bool => $this->isStale($c, $base));
        $registry = $line->quote?->opportunity?->registry;

        return [
            'payload' => [
                'quote_line_id' => $line->id,
                'product' => ['id' => $line->product?->id, 'code' => $line->product?->code, 'name' => $line->product?->name],
                'typology' => $typology ? ['id' => $typology->id, 'code' => $typology->code, 'name' => $typology->name, 'color' => $typology->color] : null,
                // Spec 0267: the client of the line's own offer, telling the lines of a multi-offer commessa apart.
                'registry' => $registry ? ['id' => $registry->id, 'name' => $registry->name] : null,
                'supplier_commission_direction' => $direction?->value,
                'quantity' => $this->money($line->quantity),
                'unit_price' => $this->money($line->unit_price),
                'net_amount' => $this->format($net),
                'supplier_commission' => $visible && $supplier->isNotEmpty() ? $this->supplierPayload($supplier->first(), $base, $supplierCents, $stale) : null,
                'commissions_amount' => $visible ? $this->format($commissions) : null,
                'net_of_commissions' => $visible ? $this->format($net - $commissions) : null,
                'effective_revenue' => $this->format($revenue),
                'warnings' => $this->warnings($received, $supplier->isEmpty(), $visible && $stale),
                'payment' => $this->paymentPayload($payment),
            ],
            'net' => $net,
            'revenue' => $revenue,
            'typology_id' => $typology?->id,
            'commissions' => $commissions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierPayload(QuoteLineCommission $first, string $base, int $amountCents, bool $stale): array
    {
        return [
            'commission_type' => $first->commission_type->value,
            'value' => number_format((float) $first->value, 4, '.', ''),
            'base_amount' => $first->commission_type === CommissionType::Percentage ? $base : null,
            'amount' => $this->format($amountCents),
            'is_stale' => $stale,
        ];
    }

    /** D-9: a PERCENTAGE amount that no longer matches the current margin base. */
    private function isStale(QuoteLineCommission $commission, string $base): bool
    {
        if ($commission->commission_type !== CommissionType::Percentage) {
            return false;
        }

        $expected = $this->calculator->calculate(new CommissionCalculationInput($commission->commission_type, (string) $commission->value, $base));

        return $this->cents($expected) !== $this->cents($commission->calculated_amount);
    }

    /**
     * @return array<int, string>
     */
    private function warnings(bool $received, bool $noSupplier, bool $stale): array
    {
        return array_values(array_filter([
            $received && $noSupplier ? self::WARNING_MISSING_SUPPLIER_COMMISSION : null,
            $stale ? self::WARNING_STALE_COMMISSION_BASE : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(?WorkOrderLinePayment $payment): array
    {
        $status = $payment?->status;

        return [
            'status' => $status ? ['id' => $status->id, 'name' => $status->name, 'color' => $status->color, 'allows_delivery' => $status->allows_delivery] : null,
            'payment_agreement' => $payment?->payment_agreement,
            'has_unpaid' => (bool) $payment?->has_unpaid,
        ];
    }

    /**
     * @param  array<int, array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows, bool $visible): array
    {
        $net = array_sum(array_column($rows, 'net'));
        $commissions = array_sum(array_column($rows, 'commissions'));

        return [
            'net_amount' => $this->format($net),
            'typologies' => $this->typologyTotals($rows),
            'effective_revenue' => $this->format(array_sum(array_column($rows, 'revenue'))),
            'commissions_amount' => $visible ? $this->format($commissions) : null,
            'net_of_commissions' => $visible ? $this->format($net - $commissions) : null,
        ];
    }

    /**
     * One entry per CONFIGURED typology, zero-filled, ordered by name then id
     * (same criterion as QuoteTypologySummaryCalculator, spec 0201 D-15).
     *
     * @param  array<int, array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}>  $rows
     * @return array<int, array{id: int, name: string, color: string, net_amount: string, effective_revenue: string}>
     */
    private function typologyTotals(array $rows): array
    {
        return ProductTypology::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'color'])
            ->map(function (ProductTypology $typology) use ($rows): array {
                $own = array_filter($rows, fn (array $row): bool => $row['typology_id'] === $typology->id);

                return [
                    'id' => $typology->id,
                    'name' => $typology->name,
                    'color' => $typology->color,
                    'net_amount' => $this->format(array_sum(array_column($own, 'net'))),
                    'effective_revenue' => $this->format(array_sum(array_column($own, 'revenue'))),
                ];
            })
            ->all();
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
