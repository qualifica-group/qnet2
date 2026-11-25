<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * The quote side of the prospetto (spec 0427 D-1), from the quote lines of
 * the work order's quote. The legacy tables become quote lines: service 1326
 * is the "learner revenue" product, expense report 8 the "learner cost"
 * product (a learner cost line is one hanging on the learner revenue line),
 * expense report 9 the partner commission product, left out of the costs.
 */
final class QuoteFigures
{
    /** Legacy employee costs (quotationemployees) come first and carry no note. */
    private const string EMPLOYEE_SOURCE = 'user_levels';

    /**
     * @param  Collection<int, QuoteLine>  $lines
     */
    private function __construct(private readonly Collection $lines) {}

    public static function of(WorkOrder $workOrder): self
    {
        $lines = $workOrder->quote_id === null
            ? collect()
            : QuoteLine::query()
                ->with(['product:id,name,old_source,old_id', 'offerLine.product:id,old_source,old_id'])
                ->where('quote_id', $workOrder->quote_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

        return new self($lines);
    }

    /** Totale Costo Discenti: the learner cost lines (`costiDiscenti`). */
    public function learnerCosts(): float
    {
        return $this->learnerCostLines()->sum(fn (QuoteLine $line): float => $this->amount($line));
    }

    /** The same total with every line rounded first, as the legacy Excel export sums it. */
    public function learnerCostsRounded(): float
    {
        return $this->learnerCostLines()->sum(fn (QuoteLine $line): float => round($this->amount($line), 2));
    }

    /** TOTALE COSTI: every cost line but the partner commissions (`calcolaTuttiCosti`). */
    public function totalCosts(): float
    {
        return $this->costLines()
            ->reject(fn (QuoteLine $line): bool => $this->is($line->product, 'partner_commission_product'))
            ->sum(fn (QuoteLine $line): float => $this->amount($line));
    }

    /**
     * The other cost lines one by one (`getAltriCostiDettaglio`): employees
     * first, zero amounts skipped.
     *
     * @return list<array{title: string, note: string, amount: float}>
     */
    public function otherCostLines(): array
    {
        return $this->costLines()
            ->reject(fn (QuoteLine $line): bool => $this->is($line->product, 'partner_commission_product') || $this->isLearnerCost($line))
            ->sortBy(fn (QuoteLine $line): int => $line->product?->old_source === self::EMPLOYEE_SOURCE ? 0 : 1)
            ->filter(fn (QuoteLine $line): bool => $this->amount($line) > 0)
            ->map(fn (QuoteLine $line): array => [
                'title' => (string) $line->product?->name,
                'note' => $line->product?->old_source === self::EMPLOYEE_SOURCE ? '' : (string) $line->additional_description,
                'amount' => $this->amount($line),
            ])
            ->values()
            ->all();
    }

    /** Totale ricavi formazione: the revenue lines but the learner revenue (`totaleRicavi`). */
    public function trainingRevenue(): float
    {
        return $this->revenueLines()
            ->reject(fn (QuoteLine $line): bool => $this->is($line->product, 'learner_revenue_product'))
            ->sum(fn (QuoteLine $line): float => $this->amount($line));
    }

    /** Revenue of the learner service alone, spread over the progress months (`ripartizioneRicaviServizi`). */
    public function learnerServiceRevenue(): float
    {
        return $this->revenueLines()
            ->filter(fn (QuoteLine $line): bool => $this->is($line->product, 'learner_revenue_product'))
            ->sum(fn (QuoteLine $line): float => $this->amount($line));
    }

    /**
     * The learner cost line ("ODA" of the legacy) of each supplier registry: the first one.
     *
     * @return array<int, array{id: int, date: string|null}>
     */
    public function learnerOrdersBySupplier(): array
    {
        $orders = [];

        foreach ($this->learnerCostLines()->sortBy('id') as $line) {
            if ($line->supplier_id !== null && ! isset($orders[$line->supplier_id])) {
                $orders[$line->supplier_id] = ['id' => $line->id, 'date' => $line->updated_at?->toDateString()];
            }
        }

        return $orders;
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function learnerCostLines(): Collection
    {
        return $this->costLines()->filter(fn (QuoteLine $line): bool => $this->isLearnerCost($line));
    }

    private function isLearnerCost(QuoteLine $line): bool
    {
        return $this->is($line->product, 'learner_cost_product') && $this->is($line->offerLine?->product, 'learner_revenue_product');
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function costLines(): Collection
    {
        return $this->lines->filter(fn (QuoteLine $line): bool => $line->line_type === QuoteLineType::Cost);
    }

    /**
     * @return Collection<int, QuoteLine>
     */
    private function revenueLines(): Collection
    {
        return $this->lines->filter(fn (QuoteLine $line): bool => $line->line_type === QuoteLineType::Revenue);
    }

    /** Legacy `quantity * price`. */
    private function amount(QuoteLine $line): float
    {
        return (float) $line->quantity * (float) $line->unit_price;
    }

    private function is(?Product $product, string $configKey): bool
    {
        $wanted = config('formalab.learner_statement.'.$configKey);

        return $product !== null
            && $product->old_source === $wanted['old_source']
            && (int) $product->old_id === (int) $wanted['old_id'];
    }
}
