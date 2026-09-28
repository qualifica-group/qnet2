<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Services\Opportunities\OpportunityNameWriter;
use App\Services\Quotes\QuoteTitleWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Re-derives every AUTOMATIC opportunity and offer title in the
 * `<code> - <products>` format (spec 0171 rev.2, D-9): the format changed and
 * migrations cannot import App\ classes, so the rows written before it keep
 * their old value until their next quote write. Manual titles are skipped by
 * the writers themselves. Idempotent: a second run changes nothing.
 */
class RecalculateTitles extends Command
{
    protected $signature = 'titles:recalculate';

    protected $description = 'Re-derive the automatic titles of opportunities and offers (<code> - <products>)';

    private const int CHUNK_SIZE = 200;

    public function handle(OpportunityNameWriter $nameWriter, QuoteTitleWriter $titleWriter): int
    {
        // Step 1: opportunities.
        $opportunities = 0;
        Opportunity::query()->where('name_is_manual', false)->chunkById(self::CHUNK_SIZE, function (Collection $rows) use ($nameWriter, &$opportunities): void {
            $rows->each(fn (Opportunity $opportunity) => $nameWriter->recalculate($opportunity->id));
            $opportunities += $rows->count();
        });

        // Step 2: offers.
        $quotes = 0;
        Quote::query()->where('title_is_manual', false)->chunkById(self::CHUNK_SIZE, function (Collection $rows) use ($titleWriter, &$quotes): void {
            $rows->each(fn (Quote $quote) => $titleWriter->recalculate($quote));
            $quotes += $rows->count();
        });

        $this->info("Recalculated {$opportunities} opportunity titles and {$quotes} offer titles.");

        return self::SUCCESS;
    }
}
