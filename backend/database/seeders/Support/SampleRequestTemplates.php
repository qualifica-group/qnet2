<?php

namespace Database\Seeders\Support;

use App\Models\Quote;
use App\Services\Quotes\RevenueProductTitleBuilder;
use Database\Seeders\QualificaSampleLeadSeeder;
use Database\Seeders\QualificaSampleRequestSeeder;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The requests the bulk sample seeder copies, produced by the REAL write path
 * and then thrown away: QualificaSampleLeadSeeder lays down free Anagrafiche,
 * QualificaSampleRequestSeeder creates a request on each through
 * RequestCreationService, their rows are read back, and the transaction is
 * rolled back. Nothing of the templates survives — not the rows, not their
 * activity log, not their in-app notifications — only their shape.
 *
 * Copying what the service wrote, instead of hand-deriving it, is what keeps
 * the bulk rows faithful: the bootstrapped workflow status, the layout, the
 * frozen line amounts and aggregates, the managers promoted onto the
 * opportunity are all the service's own output. The tables read here are the
 * footprint of a seeded request (RequestCreationService with registry, product
 * lines, team and one offer row): a new table in that footprint must be read
 * here too, or the copies silently lack it.
 */
final class SampleRequestTemplates
{
    public function __construct(private readonly RevenueProductTitleBuilder $titles) {}

    /**
     * Up to $count templates, one per offer the request seeder rotates over.
     *
     * @return list<SampleRequestTemplate>
     */
    public function capture(Container $container, int $count): array
    {
        $sinceQuoteId = (int) Quote::query()->max('id');

        DB::beginTransaction();

        try {
            // Step 1: the Anagrafiche, then one request on each — the seeders
            // stay silent, since what they report is about to be undone.
            $container->make(QualificaSampleLeadSeeder::class)->setContainer($container)
                ->__invoke(['leads' => $count, 'convertedLeads' => 0]);
            $container->make(QualificaSampleRequestSeeder::class)->setContainer($container)
                ->__invoke(['requests' => $count]);

            // Step 2: their rows, read back before they disappear.
            return Quote::query()->where('id', '>', $sinceQuoteId)->orderBy('id')->get()
                ->map(fn (Quote $quote): SampleRequestTemplate => $this->read($quote))
                ->values()
                ->all();
        } finally {
            DB::rollBack();
        }
    }

    private function read(Quote $quote): SampleRequestTemplate
    {
        $lines = $this->rows(DB::table('quote_lines')->where('quote_id', $quote->id)->orderBy('id'));

        return new SampleRequestTemplate(
            opportunity: (array) DB::table('opportunities')->where('id', $quote->opportunity_id)->sole(),
            productLines: $this->rows(DB::table('opportunity_product_lines')->where('opportunity_id', $quote->opportunity_id)->orderBy('id')),
            opportunityManagers: $this->rows(DB::table('opportunity_user')->where('opportunity_id', $quote->opportunity_id)->orderBy('position')),
            quote: (array) DB::table('quotes')->where('id', $quote->id)->sole(),
            quoteManagers: $this->rows(DB::table('quote_user')->where('quote_id', $quote->id)->orderBy('position')),
            lines: $lines,
            commissions: $this->rows(DB::table('quote_line_commissions')->whereIn('quote_line_id', array_column($lines, 'id'))->orderBy('id')),
            productNames: array_values($this->titles->quoteRevenueProductNames($quote)),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Builder $query): array
    {
        return $query->get()->map(static fn (object $row): array => (array) $row)->values()->all();
    }
}
