<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Migrations\Realign\RegistryContactsBackfill;
use App\Migrations\Realign\ReportSheetsWriter;
use Illuminate\Console\Command;

/**
 * One-off: adds to the anagrafiche already migrated the contacts the legacy now sends
 * split and cleaned (spec 0378). Without `--apply` nothing is written and the Excel
 * lists every contact that would be added. The file holds personal data: it stays on
 * the server and is never logged.
 */
class ReimportLegacyRegistryContacts extends Command
{
    private const int LEGACY_TIMEOUT_SECONDS = 120;

    private const array ADDED_HEADER = ['legacy_id', 'qnet2_id', 'registry', 'type', 'value', 'label', 'is_primary'];

    private const array SKIPPED_HEADER = ['legacy_id', 'qnet2_id', 'registry', 'reason'];

    protected $signature = 'migrations:reimport-registry-contacts
        {--apply : Write the contacts; without it the run is a simulation}
        {--output= : Path of the Excel report}';

    protected $description = 'Add to the migrated anagrafiche the contacts of the legacy QNet that are missing';

    public function handle(): int
    {
        // Step 1: a legacy page of 100 records is slower than one import page.
        config(['migrations.timeout' => self::LEGACY_TIMEOUT_SECONDS]);
        $apply = (bool) $this->option('apply');

        // Step 2: read every migrated anagrafica against the legacy; built here so the client reads the timeout.
        try {
            $result = app(RegistryContactsBackfill::class)->run($apply, fn (int $read) => $this->line("Read {$read} anagrafiche..."));
        } catch (ExternalApiException $exception) {
            $this->error("{$exception->getMessage()} The run stopped here: re-run it, contacts already added are not added twice.");

            return self::FAILURE;
        }

        // Step 3: the report, then the summary.
        $path = (string) ($this->option('output')
            ?: storage_path('app/private/realign/registry-contacts-'.now()->format('Ymd-His').'-'.($apply ? 'apply' : 'dry').'.xlsx'));
        ReportSheetsWriter::write($path, [
            'Contatti aggiunti' => [self::ADDED_HEADER, ...$result['added']],
            'Anagrafiche saltate' => [self::SKIPPED_HEADER, ...$result['skipped']],
        ]);

        $this->table(['anagrafiche read', 'contacts added', 'anagrafiche skipped'], [[$result['read'], count($result['added']), count($result['skipped'])]]);
        $this->info($apply ? 'Contacts written.' : 'Simulation: nothing was written.');
        $this->info("Report: {$path}");

        return self::SUCCESS;
    }
}
