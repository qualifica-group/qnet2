<?php

namespace Database\Seeders;

use App\Models\Quote;
use App\Services\RequestManagement\RequestOperatorWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot aligning the requests (Offerte) born from a lead with that lead:
 * `php artisan db:seed --class=LeadRequestAlignmentSeeder --force`.
 *
 * - Fonte (task 427463): the Offerte generated between spec 0332 and the fix
 *   were born without the lead's Fonte.
 * - Operatore: a lead converted before being assigned keeps an unassigned
 *   request when the operator is set on the lead afterwards.
 *
 * Only an EMPTY field is filled, never one set on the request: re-running it
 * changes nothing. The Fonte is a plain query update; the operator goes
 * through RequestOperatorWriter so the request team stays coherent. No
 * notification is sent for a data repair.
 */
class LeadRequestAlignmentSeeder extends Seeder
{
    public function __construct(private readonly RequestOperatorWriter $operators) {}

    public function run(): void
    {
        [$sources, $operators] = DB::transaction(fn (): array => [$this->alignSources(), $this->alignOperators()]);

        $this->command?->info("Richieste allineate al lead: Fonte {$sources}, Operatore {$operators}.");
    }

    private function alignSources(): int
    {
        $sourceByQuote = $this->leadBornQuotes()
            ->whereNull('quotes.source_id')
            ->whereNotNull('leads.source_id')
            ->pluck('leads.source_id', 'quotes.id');

        foreach ($sourceByQuote->groupBy(fn (int $sourceId): int => $sourceId, preserveKeys: true) as $sourceId => $quotes) {
            Quote::query()->whereIn('id', $quotes->keys())->whereNull('source_id')->toBase()->update(['source_id' => $sourceId]);
        }

        return $sourceByQuote->count();
    }

    private function alignOperators(): int
    {
        $operatorByQuote = $this->leadBornQuotes()
            ->whereNull('quotes.operator_id')
            ->whereNotNull('leads.operator_id')
            ->pluck('leads.operator_id', 'quotes.id');

        foreach ($operatorByQuote as $quoteId => $operatorId) {
            $changed = [];
            $old = [];
            $this->operators->apply(Quote::query()->findOrFail($quoteId), (int) $operatorId, $changed, $old);
        }

        return $operatorByQuote->count();
    }

    /**
     * @return Builder<Quote>
     */
    private function leadBornQuotes(): Builder
    {
        return Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->join('leads', 'leads.id', '=', 'opportunities.lead_id');
    }
}
