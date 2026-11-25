<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuoteLineType;
use App\Support\Amounts\CostVatRate;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * One-off: the costs carry no VAT (user decision 2026-11-24, the legacy QNet
 * never applied one to them). Moves every offer COST line and every work
 * order cost onto the 0% cost VAT rate (CostVatRate): VAT amount 0, total
 * equal to the net. The offer and work order totals are computed from the
 * lines, so nothing else needs rewriting. A data fix, not a user change: no
 * activity log. Idempotent: a second run touches nothing.
 */
class ClearCostVat extends Command
{
    protected $signature = 'costs:clear-vat {--dry-run : Report what would change without writing}';

    protected $description = 'Move every offer cost line and work order cost onto the 0% cost VAT rate';

    public function handle(): int
    {
        // Step 1: the target rate, which must exist.
        $rate = CostVatRate::resolve();

        if ($rate === null) {
            $this->error('VAT rate "'.config('work-orders.cost_vat_rate_name').'" at 0% not found: create it or set COST_VAT_RATE_NAME.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        // Step 2: the two cost tables, each restricted to the rows not yet on it.
        $targets = [
            'offer cost line(s)' => $this->notOnRate(DB::table('quote_lines')->where('line_type', QuoteLineType::Cost->value), $rate->id),
            'work order cost(s)' => $this->notOnRate(DB::table('work_order_costs'), $rate->id),
        ];

        // Step 3: count or move, all or nothing.
        $counts = DB::transaction(fn (): array => array_map(
            fn (Builder $query): int => $dryRun ? $query->count() : $this->moveToRate($query, $rate->id),
            $targets,
        ));

        foreach ($counts as $label => $count) {
            $this->info($dryRun
                ? "{$count} {$label} would be set to {$rate->name}."
                : "{$count} {$label} set to {$rate->name}.");
        }

        return self::SUCCESS;
    }

    private function notOnRate(Builder $query, int $rateId): Builder
    {
        return $query->where(static fn (Builder $where): Builder => $where
            ->whereNull('vat_rate_id')
            ->orWhere('vat_rate_id', '<>', $rateId)
            ->orWhere('vat_amount', '<>', 0)
            ->orWhereColumn('total_amount', '<>', 'net_amount'));
    }

    private function moveToRate(Builder $query, int $rateId): int
    {
        return $query->update([
            'vat_rate_id' => $rateId,
            'vat_amount' => 0,
            'total_amount' => DB::raw('net_amount'),
        ]);
    }
}
