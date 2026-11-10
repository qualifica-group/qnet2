<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Migrations\Realign\LegacyRealignReport;
use App\Migrations\Realign\LegacyRealignReportWriter;
use App\Migrations\Realign\LegacyRealignRunner;
use App\Migrations\Realign\LegacyRealignTargets;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * One-off alignment of the records already on qnet-2 to what changed in the legacy
 * (spec 0375). Without `--apply` nothing is written and the Excel shows every field
 * that would change; `--apply` runs the same code and commits.
 */
class RealignLegacyRecords extends Command
{
    private const int LEGACY_TIMEOUT_SECONDS = 120;

    private const string DATE_FORMAT = 'Y-m-d';

    protected $signature = 'migrations:realign-legacy
        {--since= : Y-m-d, the first day of the changes to align}
        {--apply : Write the changes; without it the run is a simulation}
        {--sources= : Comma-separated source keys; default all}
        {--output= : Path of the Excel report}';

    protected $description = 'Align the records already on qnet-2 to the changes made in the legacy QNet since a date';

    public function handle(LegacyRealignReportWriter $writer): int
    {
        // Step 1: refuse bad options before any call.
        $since = $this->since();
        $sources = $this->sources();

        if ($since === null || $sources === null) {
            return self::FAILURE;
        }

        // Step 2: the listing of a big module is slower than one import page.
        config(['migrations.timeout' => self::LEGACY_TIMEOUT_SECONDS]);

        // Step 3: align module by module; the report is written even after a stop.
        $apply = (bool) $this->option('apply');
        $report = new LegacyRealignReport;
        $result = $this->alignAll($sources, $since, $apply, $report);

        $path = $this->outputPath($apply);
        $writer->write($report, $path, $apply);
        $this->printSummary($report, $path, $apply);

        return $result;
    }

    /**
     * @param  list<string>  $sources
     */
    private function alignAll(array $sources, string $since, bool $apply, LegacyRealignReport $report): int
    {
        // Built here, not injected: the client reads the timeout set just above.
        $runner = app(LegacyRealignRunner::class);

        foreach ($sources as $source) {
            $this->line("Aligning {$source}...");

            try {
                $runner->run(app(LegacyRealignTargets::BY_SOURCE[$source]), $since, $apply, $report);
            } catch (ExternalApiException $exception) {
                $this->error("{$source}: {$exception->getMessage()} The run stopped here.");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function since(): ?string
    {
        $value = (string) $this->option('since');
        $date = DateTimeImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);

        if ($date === false || $date->format(self::DATE_FORMAT) !== $value) {
            $this->error('--since is required, in the format YYYY-MM-DD.');

            return null;
        }

        return $value;
    }

    /**
     * @return list<string>|null
     */
    private function sources(): ?array
    {
        $option = trim((string) $this->option('sources'));

        if ($option === '') {
            return array_keys(LegacyRealignTargets::BY_SOURCE);
        }

        $requested = array_map(trim(...), explode(',', $option));
        $unknown = array_diff($requested, array_keys(LegacyRealignTargets::BY_SOURCE));

        if ($unknown !== []) {
            $this->error('Unknown source: '.implode(', ', $unknown).'.');

            return null;
        }

        // Always parents first, whatever order was typed.
        return array_values(array_intersect(array_keys(LegacyRealignTargets::BY_SOURCE), $requested));
    }

    private function outputPath(bool $apply): string
    {
        return (string) ($this->option('output')
            ?: storage_path('app/private/realign/realign-'.now()->format('Ymd-His').'-'.($apply ? 'apply' : 'dry').'.xlsx'));
    }

    private function printSummary(LegacyRealignReport $report, string $path, bool $apply): void
    {
        $this->table(['module', ...LegacyRealignReport::COUNTERS], collect($report->counters)
            ->map(static fn (array $counters, string $module): array => [$module, ...array_values($counters)])
            ->values()
            ->all());
        $this->info($apply ? 'Changes written.' : 'Simulation: nothing was written.');
        $this->info("Report: {$path}");
    }
}
