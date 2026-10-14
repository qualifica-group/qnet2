<?php

namespace Database\Seeders;

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\Sector;
use App\Models\User;
use App\Services\MigrationService;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * The legacy import (spec 0013 / 0046). A step of QualificaProductionDataSeeder
 * that stays runnable on its own
 * (`php artisan db:seed --class=QualificaLegacyImportSeeder`), but only AFTER
 * QualificaCatalogSeeder — it adopts that seeder's source catalogue and its
 * "Formazione" / "APL" business functions by name.
 *
 * It pulls the client's real catalogues from the external system through the
 * SAME engine the "Migrazioni" section uses — one MassMigrationRun with one
 * child MigrationRun per source, run inline via MigrationService::runMassSync.
 * No catalogue is hard-coded here: the values are whatever the legacy system
 * holds, and the run is visible in the Migrazioni history like a UI-launched
 * one. Where the legacy product taxonomy lands, the "Old category" container,
 * is the sources' own rule (LegacyCategoryContainer), not this seeder's.
 *
 * Idempotent by construction: every source skips a record whose `old_id` is
 * already imported, and the sources and business functions
 * QualificaCatalogSeeder also provisions by name are adopted rather than
 * duplicated (SourcesSource, BusinessFunctionsSource). The product categories
 * are never adopted: the legacy sends them as "[OLD] ..." (user directive
 * 2026-10-13). Re-running therefore only pulls in what the legacy system has
 * that qnet does not.
 *
 * Both preconditions are optional, never fatal: with no external system
 * configured, or no super-admin to act as, the static catalogue stands on its
 * own and this step is skipped with a warning.
 */
class QualificaLegacyImportSeeder extends Seeder
{
    /**
     * The catalogues imported here, in MigrationOrder phase order — a
     * later entry resolves its references against the earlier ones via
     * `old_id`. Deliberately a fixed subset of the mass-import plan: users
     * and referents are operational data, not template data. The legacy
     * costs (`cost-products`, spec 0174) and the sellable catalogue
     * (`products`, user directive 2026-09-29) are in, by user request.
     *
     * @var list<string>
     */
    public const array SOURCES = [
        // Phase 1 — independent anchors, none references another.
        'business-functions',
        'companies',
        'operational-sites',
        'referent-types',
        'sources',
        'tags',
        'sectors',
        'vat-rates',
        'payment-methods',
        // The task templates (spec 0172) are template data too: stages,
        // actions and sub-actions of every legacy model, no cross-source link.
        'task-templates',
        // "Modelli email" / "Modelli documenti" (spec 0175, D-13) are template
        // data too, same category as `task-templates`: no cross-source link.
        'email-templates',
        'document-bundles',
        // Phase 2 — company sites ("Societa Sedi") resolve their owning
        // company through the phase-1 `companies` old_id, so they can only run
        // once that anchor is in. The rest of phase 2 (users, referents) stays
        // out: operational data, not template data.
        'company-sites',
        // Phase 4 — product anchors: the attribute catalogue and the category
        // tree, neither of which carries the pivot between them.
        'attributes',
        'product-categories',
        // Phase 5 — the association pass that back-fills that pivot, once both
        // anchors have their `old_id`.
        'product-category-attributes',
        // Phase 6 — the category form layouts, valid only once the links above exist.
        'attribute-layouts',
        // The sellable catalogue: remaps its category through the phase-4
        // `product-categories` and its VAT through the phase-1 `vat-rates`.
        'products',
        // The legacy costs (spec 0174): cost-only products under their own
        // "[OLD] Costi" branch, VAT remapped through the phase-1 `vat-rates`.
        'cost-products',
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
        $run = app(MigrationService::class)->runMassSync($actor, self::SOURCES);

        // Step 4: the legacy sectors are history only (spec 0213 D-2).
        $this->deactivateLegacySectors();

        $this->report($run);
    }

    /**
     * Every sector with an `old_id` is left NOT active (spec 0213 D-2): the
     * client classifies with the EA catalogue QualificaSectorSeeder provides,
     * while the legacy rows stay valid on the registries already linked to
     * them. On EVERY run, by user decision, including a legacy sector someone
     * reactivated by hand.
     *
     * Per-model update (not a mass query update) so the activity log records
     * the change, as every other write on this model does.
     */
    private function deactivateLegacySectors(): void
    {
        $active = Sector::query()->whereNotNull('old_id')->where('is_active', true)->get();

        $active->each(fn (Sector $sector) => $sector->update(['is_active' => false]));

        if ($active->isNotEmpty()) {
            $this->command?->info(sprintf('%d legacy sectors deactivated.', $active->count()));
        }
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
