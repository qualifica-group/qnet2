<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CommissionAccrual;
use App\Models\Invoice;
use App\Services\Commissions\Accruals\CommissionAccrualSynchronizer;
use App\Services\Commissions\Accruals\SyncResult;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds / realigns the commission accrual ledger (spec 0212, D-7): every
 * invoice with a collection or a movement goes through the synchronizer, so a
 * second run creates nothing. `--settled-before` flags the movements older than
 * the date as settled outside the system; `--dry-run` rolls everything back.
 * Scheduled nightly in routes/console.php, without --settled-before.
 */
class SyncCommissionAccruals extends Command
{
    protected $signature = 'commissions:sync-accruals {--invoice=* : limit to these invoice ids} {--from= : only invoices dated on or after YYYY-MM-DD} {--settled-before= : mark the movements accrued before YYYY-MM-DD as settled externally} {--dry-run : compute and print the counts without writing}';

    protected $description = 'Synchronize the commission accrual ledger with the invoice collections';

    private const int CHUNK = 200;

    public function __construct(private readonly CommissionAccrualSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        // Step 1: validate the dates before any write
        $from = $this->dateOption('from');
        $settledBefore = $this->dateOption('settled-before');

        if ($from === false || $settledBefore === false) {
            $this->error('Invalid date: use the YYYY-MM-DD format.');

            return self::FAILURE;
        }

        // Step 2: one transaction per invoice (the synchronizer's own); a dry run wraps all in one to roll it back
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_map('intval', (array) $this->option('invoice'));
        $dryRun && DB::beginTransaction();

        try {
            [$invoices, $result] = $this->synchronize($ids, $from);
            $external = $settledBefore === null ? 0 : $this->markExternal($settledBefore, $ids);
        } finally {
            $dryRun && DB::rollBack();
        }

        // Step 3: report
        $this->info("Invoices: {$invoices}, accruals: {$result->accruals}, reversals: {$result->reversals}, marked external: {$external}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{0: int, 1: SyncResult}
     */
    private function synchronize(array $ids, ?string $from): array
    {
        $count = 0;
        $result = new SyncResult;

        Invoice::query()
            ->where(fn ($query) => $query
                ->whereHas('installments', fn ($installments) => $installments->where('collected_amount', '>', 0))
                ->orWhereIn('id', CommissionAccrual::query()->select('invoice_id')->whereNotNull('invoice_id')))
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($from !== null, fn ($query) => $query->whereDate('document_date', '>=', $from))
            ->chunkById(self::CHUNK, function ($chunk) use (&$count, &$result): void {
                foreach ($chunk as $invoice) {
                    $result = $result->plus($this->synchronizer->syncInvoice($invoice));
                    $count++;
                }
            });

        return [$count, $result];
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function markExternal(string $date, array $ids): int
    {
        return CommissionAccrual::query()
            ->whereNull('settled_externally_on')
            ->whereDate('accrued_on', '<', $date)
            ->when($ids !== [], fn ($query) => $query->whereIn('invoice_id', $ids))
            ->update(['settled_externally_on' => $date]);
    }

    /** The option as a valid YYYY-MM-DD date, null when absent, false when malformed. */
    private function dateOption(string $name): string|false|null
    {
        $value = $this->option($name);

        if ($value === null || $value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', (string) $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : false;
    }
}
