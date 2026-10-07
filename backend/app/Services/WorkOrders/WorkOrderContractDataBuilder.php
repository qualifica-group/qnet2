<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\Commissions\CommissionCalculationInput;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\ProductTypology;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use App\Services\Commissions\QuoteLineCommissionBaseResolver;
use Illuminate\Support\Collection;

/**
 * Builds the "Dati contrattuali" payload of a commessa (spec 0201). Every
 * figure comes from data already persisted on the offer (D-7), nothing is
 * recalculated into storage; amounts are summed as integer cents.
 *
 * Per line: "effective revenue" is the Fornitore commission for an Ente line and
 * the net amount for any other typology (D-1/D-8), next to the spec 0145
 * "net of commissions". When a line carries several Fornitore commissions,
 * `amount` is their sum while `commission_type`/`value`/`base_amount` describe
 * the first one. The commission details are hidden (null) unless the actor sees
 * the offer's `commissions` and `commission_value` fields (D-10), the same rule
 * as QuoteResource::summarizeTotals.
 */
final class WorkOrderContractDataBuilder
{
    /** Typology code of an Ente product (spec 0201, D-8). */
    private const string INSTITUTION_TYPOLOGY_CODE = 'institution';

    public const string WARNING_MISSING_SUPPLIER_COMMISSION = 'missing_supplier_commission';

    public const string WARNING_STALE_COMMISSION_BASE = 'stale_commission_base';

    /** Relations read per line, eager loaded to avoid N+1. */
    private const array LINE_RELATIONS = ['product.productTypology', 'commissions', 'payment.status'];

    public function __construct(
        private readonly QuoteCommissionPayloadRedactor $redactor,
        private readonly QuoteLineCommissionBaseResolver $baseResolver,
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, mixed>, commissions_visible: bool}
     */
    public function build(WorkOrder $workOrder, User $actor): array
    {
        // Step 1: load the commessa's lines and decide the commission visibility
        $visible = $this->commissionsVisible($workOrder, $actor);
        $lines = $workOrder->quoteLines()->with(self::LINE_RELATIONS)->orderBy('quote_lines.id')->get();

        // Step 2: figures per line
        $rows = $this->rows($lines, $visible);

        return [
            'lines' => array_column($rows, 'payload'),
            'totals' => $this->totals($rows, $visible),
            'commissions_visible' => $visible,
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

        return $this->rows(collect([$line]), $this->commissionsVisible($workOrder, $actor))[0]['payload'];
    }

    private function commissionsVisible(WorkOrder $workOrder, User $actor): bool
    {
        $permissions = $this->redactor->permissions($actor, $workOrder->loadMissing('quote')->quote);

        return $permissions['commissions']->visible && $permissions['commission_value']->visible;
    }

    /**
     * @param  Collection<int, QuoteLine>  $lines
     * @return array<int, array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}>
     */
    private function rows(Collection $lines, bool $visible): array
    {
        $bases = $this->baseResolver->resolveForLines($lines);

        return $lines->map(fn (QuoteLine $line): array => $this->row($line, $bases[$line->id], $visible))->values()->all();
    }

    /**
     * @return array{payload: array<string, mixed>, net: int, revenue: int, typology_id: int|null, commissions: int}
     */
    private function row(QuoteLine $line, string $base, bool $visible): array
    {
        $typology = $line->product?->productTypology;
        $institution = $typology?->code === self::INSTITUTION_TYPOLOGY_CODE;
        $supplier = $line->commissions->filter(fn (QuoteLineCommission $c): bool => $c->recipient_role === CommissionRecipientRole::Supplier)->sortBy('id')->values();

        $net = $this->cents($line->net_amount);
        $supplierCents = $supplier->sum(fn (QuoteLineCommission $c): int => $this->cents($c->calculated_amount));
        $commissions = $line->commissions->sum(fn (QuoteLineCommission $c): int => $this->cents($c->calculated_amount));
        $revenue = $institution ? $supplierCents : $net;
        $stale = $supplier->contains(fn (QuoteLineCommission $c): bool => $this->isStale($c, $base));

        return [
            'payload' => [
                'quote_line_id' => $line->id,
                'product' => ['id' => $line->product?->id, 'code' => $line->product?->code, 'name' => $line->product?->name],
                'typology' => $typology ? ['id' => $typology->id, 'code' => $typology->code, 'name' => $typology->name] : null,
                'is_institution' => $institution,
                'quantity' => $this->money($line->quantity),
                'unit_price' => $this->money($line->unit_price),
                'net_amount' => $this->format($net),
                'supplier_commission' => $visible && $supplier->isNotEmpty() ? $this->supplierPayload($supplier->first(), $base, $supplierCents, $stale) : null,
                'commissions_amount' => $visible ? $this->format($commissions) : null,
                'net_of_commissions' => $visible ? $this->format($net - $commissions) : null,
                'effective_revenue' => $this->format($revenue),
                'warnings' => $this->warnings($institution, $supplier->isEmpty(), $visible && $stale),
                'payment' => $this->paymentPayload($line),
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
    private function warnings(bool $institution, bool $noSupplier, bool $stale): array
    {
        return array_values(array_filter([
            $institution && $noSupplier ? self::WARNING_MISSING_SUPPLIER_COMMISSION : null,
            $stale ? self::WARNING_STALE_COMMISSION_BASE : null,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(QuoteLine $line): array
    {
        $payment = $line->payment;
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
     * @return array<int, array{id: int, name: string, net_amount: string, effective_revenue: string}>
     */
    private function typologyTotals(array $rows): array
    {
        return ProductTypology::query()->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(function (ProductTypology $typology) use ($rows): array {
                $own = array_filter($rows, fn (array $row): bool => $row['typology_id'] === $typology->id);

                return [
                    'id' => $typology->id,
                    'name' => $typology->name,
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
