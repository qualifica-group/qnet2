<?php

namespace Database\Seeders;

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\User;
use App\Services\MigrationService;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * Legacy-import step of the Qualifica template seed (spec 0013 / 0046): pulls
 * the client's real catalogues from the external system through the SAME
 * engine the "Migrazioni" section uses — one MassMigrationRun with one child
 * MigrationRun per source, run inline via MigrationService::runMassSync. No
 * catalogue is hard-coded here: the values are whatever the legacy system
 * holds, and the run is visible in the Migrazioni history like a UI-launched
 * one.
 *
 * Idempotent by construction: every source skips a record whose `old_id` is
 * already imported, and `sources` — the one catalogue the static template
 * also provisions by name — is adopted rather than duplicated
 * (SourcesSource). Re-running the template therefore only pulls in what the
 * legacy system has that qnet does not.
 *
 * Both preconditions are optional, never fatal: with no external system
 * configured, or no super-admin to act as, the static template stands on its
 * own and this step is skipped with a warning.
 */
class QualificaLegacyImportSeeder extends Seeder
{
    /**
     * The catalogues the template imports, in MigrationOrder phase-1 order —
     * they are independent anchors, none references another via `old_id`.
     * Deliberately a fixed subset of the mass-import plan: users, referents
     * and the product tree are operational data, not template data.
     *
     * @var list<string>
     */
    public const array SOURCES = [
        'business-functions',
        'companies',
        'operational-sites',
        'referent-types',
        'sources',
        'tags',
        'sectors',
    ];

    public function run(): void
    {
        // Step 1: the external system is optional (env-backed, empty by
        // default) — without it there is nothing to import.
        if (blank(config('migrations.base_url'))) {
            $this->command?->warn('Legacy import skipped: EXTERNAL_MIGRATION_BASE_URL is not configured.');

            return;
        }

        // Step 2: the import acts on behalf of a super-admin — the role the
        // Migrazioni section is gated on, and the actor the domain Services
        // create every record for.
        // whereHas, not the `role()` scope: the scope throws when the role
        // itself does not exist yet (a database seeded without
        // RolePermissionSeeder), which here is just "nobody to run as".
        $actor = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserService::PRIVILEGED_ROLE))
            ->orderBy('id')
            ->first();

        if ($actor === null) {
            $this->command?->warn('Legacy import skipped: no super-admin user to run the import as.');

            return;
        }

        // Step 3: one mass run over the fixed source list, executed inline.
        $this->report(app(MigrationService::class)->runMassSync($actor, self::SOURCES));
    }

    private function report(MassMigrationRun $run): void
    {
        $runs = $run->runs()->get();

        $this->command?->info(sprintf(
            'Legacy import %s: %d created, %d skipped, %d failed across %d sources.',
            $run->status->value,
            $runs->sum('created_rows'),
            $runs->sum('skipped_rows'),
            $runs->sum('failed_rows'),
            $runs->count(),
        ));

        $failed = $runs->firstWhere('status', MigrationStatus::Failed);

        if ($failed !== null) {
            // The orchestrator stops at the first failing source, so the later
            // ones never ran: say which one to re-launch from.
            $this->command?->warn(sprintf('Chain stopped at "%s": the following sources did not run.', $failed->source));
        }
    }
}
