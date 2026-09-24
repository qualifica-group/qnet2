<?php

namespace App\Console\Commands;

use Database\Seeders\QualificaSampleContractSeeder;
use Database\Seeders\QualificaSampleDataSeeder;
use Database\Seeders\QualificaSampleLeadSeeder;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Database\Seeders\QualificaSampleQuoteSeeder;
use Database\Seeders\QualificaSampleRequestSeeder;
use Database\Seeders\QualificaSampleTaskSeeder;
use Database\Seeders\QualificaSampleTimeEntrySeeder;
use Database\Seeders\QualificaSampleWorkOrderSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use InvalidArgumentException;

/**
 * Terminal front-end over QualificaSampleDataSeeder (user directive
 * 2026-09-08): the batch sizes as flags, so a bigger dataset is one run with
 * `--leads=200` rather than five runs of `db:seed --class=`.
 *
 * `--size=N` (user directive 2026-09-24) sizes every domain at once: N of
 * each, with the leads scaled so the batch has the Anagrafiche it needs — N
 * converted leads plus one free Anagrafica for each of the N opportunities
 * and N requests (an anagrafica carries ONE open opportunity at a time). A
 * per-domain flag still wins over it.
 *
 * A front-end, never a second implementation: the flags become the
 * seeder's own `run()` parameters and the chain does the rest, so
 * `db:seed --class=QualificaSampleDataSeeder` (defaults) and this command
 * cannot drift apart.
 *
 * ConfirmableTrait for the same reason `db:seed` carries it: this writes
 * fabricated rows, and doing that to a production database has to be a
 * deliberate `--force`, never a fast typo.
 */
class SeedSampleData extends Command
{
    use ConfirmableTrait;

    /** Leads per `--size` unit: N converted + N free for the opportunities + N free for the requests. */
    private const int LEADS_PER_SIZE = 3;

    protected $signature = 'qualifica:seed-sample
        {--size= : How many rows of EVERY domain to append (a per-domain flag below still wins)}
        {--leads= : How many leads to append}
        {--converted-leads= : How many of those leads convert into an opportunity (a cap: at most one lead in three converts)}
        {--opportunities= : How many lead-less opportunities to append}
        {--requests= : How many Gestione Richieste requests to append}
        {--quotes= : How many of the new opportunities get an offer (those without one; capped by how many exist)}
        {--contracts= : How many offers close as won and open a contract (only branches sold under a contract)}
        {--work-orders= : How many validated contracts are programmed into a work order}
        {--tasks= : How many tasks to append on the new work orders and opportunities (the completed ones log their own time entry)}
        {--time-entries= : How many further time entries to log on the new tasks, work orders and opportunities}
        {--force : Run without asking for confirmation in production}';

    protected $description = 'Append a batch of sample rows (leads, opportunities, requests, quotes, contracts, work orders, tasks, time entries) on top of the production seed';

    public function handle(): int
    {
        // Step 1: fabricated rows on a production database are never an
        // accident worth making silent.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // Step 2: the batch sizes — the per-domain flag, else `--size`, else
        // the seeder's own default.
        try {
            $sizes = $this->batchSizes();
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::INVALID;
        }

        // Step 3: the chain itself, driven exactly as `db:seed --class=`
        // drives it — same seeder, same order, same output.
        $this->laravel->make(QualificaSampleDataSeeder::class)
            ->setContainer($this->laravel)
            ->setCommand($this)
            ->__invoke($sizes);

        return self::SUCCESS;
    }

    /**
     * @return array{leads: int, convertedLeads: int, opportunities: int, requests: int, quotes: int, contracts: int, workOrders: int, tasks: int, timeEntries: int}
     *
     * @throws InvalidArgumentException a flag carries something other than a positive integer
     */
    private function batchSizes(): array
    {
        $size = $this->positiveOption('size', 0);
        $default = static fn (int $seederDefault, int $perSize = 1): int => $size > 0 ? $size * $perSize : $seederDefault;

        return [
            'leads' => $this->positiveOption('leads', $default(QualificaSampleLeadSeeder::DEFAULT_LEADS, self::LEADS_PER_SIZE)),
            'convertedLeads' => $this->positiveOption('converted-leads', $default(QualificaSampleLeadSeeder::DEFAULT_CONVERTED_LEADS)),
            'opportunities' => $this->positiveOption('opportunities', $default(QualificaSampleOpportunitySeeder::DEFAULT_OPPORTUNITIES)),
            'requests' => $this->positiveOption('requests', $default(QualificaSampleRequestSeeder::DEFAULT_REQUESTS)),
            'quotes' => $this->positiveOption('quotes', $default(QualificaSampleQuoteSeeder::DEFAULT_QUOTES)),
            'contracts' => $this->positiveOption('contracts', $default(QualificaSampleContractSeeder::DEFAULT_CONTRACTS)),
            'workOrders' => $this->positiveOption('work-orders', $default(QualificaSampleWorkOrderSeeder::DEFAULT_WORK_ORDERS)),
            'tasks' => $this->positiveOption('tasks', $default(QualificaSampleTaskSeeder::DEFAULT_TASKS)),
            'timeEntries' => $this->positiveOption('time-entries', $default(QualificaSampleTimeEntrySeeder::DEFAULT_TIME_ENTRIES)),
        ];
    }

    /**
     * An absent flag takes $default; a present one must be a positive integer.
     * Casting blindly would turn `--leads=abc` into 0 and seed an empty batch
     * while reporting success — a silent failure, not a default.
     *
     * @throws InvalidArgumentException
     */
    private function positiveOption(string $name, int $default): int
    {
        $value = $this->option($name);

        if ($value === null) {
            return $default;
        }

        if (! ctype_digit((string) $value) || (int) $value < 1) {
            throw new InvalidArgumentException(sprintf('--%s must be a positive integer, got "%s".', $name, $value));
        }

        return (int) $value;
    }
}
