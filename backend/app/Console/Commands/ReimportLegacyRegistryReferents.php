<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ExternalApiException;
use App\Migrations\Realign\RegistryReferentsBackfill;
use App\Migrations\Realign\ReportSheetsWriter;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off: links to the anagrafiche already migrated "Referente 1" and "Referente 2" of
 * the legacy QNet and moves the principale off the Commerciale (spec 0398). Without
 * `--apply` nothing is written and the Excel lists every change. The file holds
 * personal data: it stays on the server and is never logged.
 */
class ReimportLegacyRegistryReferents extends Command
{
    private const int LEGACY_TIMEOUT_SECONDS = 120;

    private const string ACTOR_ROLE = 'super-admin';

    private const array LINKED_HEADER = ['legacy_id', 'qnet2_id', 'registry', 'slot', 'referent_id', 'referent', 'created', 'contacts_added'];

    private const array PRIMARY_HEADER = ['legacy_id', 'qnet2_id', 'registry', 'old_primary', 'new_primary', 'reason'];

    private const array SKIPPED_HEADER = ['legacy_id', 'qnet2_id', 'registry', 'reason'];

    protected $signature = 'migrations:reimport-registry-referents
        {--apply : Write the referents; without it the run is a simulation}
        {--output= : Path of the Excel report}';

    protected $description = 'Link Referente 1/2 of the legacy QNet to the migrated anagrafiche and fix their principale';

    public function handle(): int
    {
        // Step 1: the referents are created on behalf of a super-admin, as at import.
        $actor = User::role(self::ACTOR_ROLE)->orderBy('id')->first();

        if ($actor === null) {
            $this->error('No super-admin user: the referents need one to be created.');

            return self::FAILURE;
        }

        config(['migrations.timeout' => self::LEGACY_TIMEOUT_SECONDS]);
        $apply = (bool) $this->option('apply');

        // Step 2: every migrated anagrafica against the legacy; built here so the client reads the timeout.
        try {
            $result = app(RegistryReferentsBackfill::class)->run($apply, $actor, fn (int $read) => $this->line("Read {$read} anagrafiche..."));
        } catch (ExternalApiException $exception) {
            $this->error("{$exception->getMessage()} The run stopped here: re-run it, what is already written is not repeated.");

            return self::FAILURE;
        }

        // Step 3: the report, then the summary.
        $path = (string) ($this->option('output')
            ?: storage_path('app/private/realign/registry-referents-'.now()->format('Ymd-His').'-'.($apply ? 'apply' : 'dry').'.xlsx'));
        ReportSheetsWriter::write($path, [
            'Referenti collegati' => [self::LINKED_HEADER, ...$result['linked']],
            'Principali cambiati' => [self::PRIMARY_HEADER, ...$result['primaries']],
            'Anagrafiche saltate' => [self::SKIPPED_HEADER, ...$result['skipped']],
        ]);

        $created = count(array_filter($result['linked'], static fn (array $row): bool => $row[6] === true));
        $this->table(
            ['anagrafiche read', 'referents created', 'referents matched', 'principali changed', 'principali kept (changed by hand)'],
            [[$result['read'], $created, count($result['linked']) - $created, count($result['primaries']), $result['kept_manual']]],
        );
        $this->info($apply ? 'Referents written.' : 'Simulation: nothing was written.');
        $this->info("Report: {$path}");

        return self::SUCCESS;
    }
}
