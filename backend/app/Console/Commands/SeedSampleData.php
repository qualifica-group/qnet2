<?php

namespace App\Console\Commands;

use Database\Seeders\QualificaSampleDataSeeder;
use Database\Seeders\QualificaSampleLeadSeeder;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Database\Seeders\QualificaSampleRequestSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use InvalidArgumentException;

/**
 * Terminal front-end over QualificaSampleDataSeeder (user directive
 * 2026-09-08): the batch sizes as flags, so a bigger dataset is one run with
 * `--leads=200` rather than five runs of `db:seed --class=`.
 *
 * A front-end, never a second implementation: the four flags become the
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

    protected $signature = 'qualifica:seed-sample
        {--leads= : How many leads to append}
        {--converted-leads= : How many of those leads convert into an opportunity (a cap: at most one lead in three converts)}
        {--opportunities= : How many lead-less opportunities to append}
        {--requests= : How many Gestione Richieste requests to append}
        {--force : Run without asking for confirmation in production}';

    protected $description = 'Append a batch of sample rows (leads, opportunities, requests) on top of the production seed';

    public function handle(): int
    {
        // Step 1: fabricated rows on a production database are never an
        // accident worth making silent.
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // Step 2: the four batch sizes, each falling back to its own seeder's
        // default when the flag is absent.
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
     * @return array{leads: int, convertedLeads: int, opportunities: int, requests: int}
     *
     * @throws InvalidArgumentException a flag carries something other than a positive integer
     */
    private function batchSizes(): array
    {
        return [
            'leads' => $this->positiveOption('leads', QualificaSampleLeadSeeder::DEFAULT_LEADS),
            'convertedLeads' => $this->positiveOption('converted-leads', QualificaSampleLeadSeeder::DEFAULT_CONVERTED_LEADS),
            'opportunities' => $this->positiveOption('opportunities', QualificaSampleOpportunitySeeder::DEFAULT_OPPORTUNITIES),
            'requests' => $this->positiveOption('requests', QualificaSampleRequestSeeder::DEFAULT_REQUESTS),
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
