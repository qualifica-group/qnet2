<?php

namespace Database\Seeders;

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\MigrationService;
use App\Services\UserService;
use Illuminate\Database\Seeder;

/**
 * The legacy import (spec 0013 / 0046), a STANDALONE seeder: it is not
 * chained by QualificaTemplateSeeder, it is launched on its own
 * (`php artisan db:seed --class=QualificaLegacyImportSeeder`) and must run
 * AFTER it — it adopts that seeder's source catalogue by name, and
 * nestImportedCategories() needs the "Consulenza" root it creates.
 *
 * It pulls the client's real catalogues from the external system through the
 * SAME
 * engine the "Migrazioni" section uses — one MassMigrationRun with one child
 * MigrationRun per source, run inline via MigrationService::runMassSync. No
 * catalogue is hard-coded here: the values are whatever the legacy system
 * holds, and the run is visible in the Migrazioni history like a UI-launched
 * one. The one shape decision the seed does impose is where the imported
 * product taxonomy lands — under the template's "Consulenza" root, see
 * nestImportedCategories().
 *
 * Idempotent by construction: every source skips a record whose `old_id` is
 * already imported, and `sources` — the one catalogue the template also
 * provisions by name — is adopted rather than duplicated (SourcesSource).
 * Re-running therefore only pulls in what the legacy system has that qnet
 * does not.
 *
 * Both preconditions are optional, never fatal: with no external system
 * configured, or no super-admin to act as, the static template stands on its
 * own and this step is skipped with a warning.
 */
class QualificaLegacyImportSeeder extends Seeder
{
    /**
     * The catalogues imported here, in MigrationOrder phase order — a
     * later entry resolves its references against the earlier ones via
     * `old_id`. Deliberately a fixed subset of the mass-import plan: users,
     * referents and `products` are operational data, not template data.
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
        // Phase 4 — product anchors: the attribute catalogue and the category
        // tree, neither of which carries the pivot between them.
        'attributes',
        'product-categories',
        // Phase 5 — the association pass that back-fills that pivot, once both
        // anchors have their `old_id`.
        'product-category-attributes',
    ];

    /**
     * Root the legacy product taxonomy is nested under: the client's imported
     * categories hang below "Consulenza" (a root the static catalogue seeds),
     * never beside it at the top level. Must match a root name of
     * QualificaTemplateSeeder's CATALOG.
     */
    private const string LEGACY_CATEGORY_ROOT = 'Consulenza';

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

        // Step 4: reparent the freshly imported taxonomy under the client root.
        $this->nestImportedCategories();

        $this->report($run);
    }

    /**
     * Move every migrated product category still sitting at top level under
     * LEGACY_CATEGORY_ROOT: a legacy root, or one ProductCategoriesSource left
     * detached because its own parent never migrated (the run report carries
     * that warning) — neither belongs beside the template's own roots.
     * Categories without an `old_id` are the static template's tree and are
     * never touched, so re-running moves nothing a second time.
     *
     * A direct `parent_id` write, mirroring the engine's own relink pass
     * (ProductCategoriesSource::afterImport): the tree is a plain adjacency
     * list with no derived column to maintain.
     */
    private function nestImportedCategories(): void
    {
        $root = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', self::LEGACY_CATEGORY_ROOT)
            ->first();

        if ($root === null) {
            $this->command?->warn(sprintf(
                'Imported product categories left at top level: no "%s" root category found.',
                self::LEGACY_CATEGORY_ROOT,
            ));

            return;
        }

        $orphans = ProductCategory::query()
            ->whereNotNull('old_id')
            ->whereNull('parent_id')
            ->whereKeyNot($root->getKey())
            ->get();

        // Per-model update (not a mass query update) so the activity log
        // records the reparent, as every other write on this model does.
        $orphans->each(fn (ProductCategory $category) => $category->update(['parent_id' => $root->getKey()]));

        if ($orphans->isNotEmpty()) {
            $this->command?->info(sprintf(
                '%d imported product categories nested under "%s".',
                $orphans->count(),
                self::LEGACY_CATEGORY_ROOT,
            ));
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
