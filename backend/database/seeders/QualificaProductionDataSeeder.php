<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The client's production-like dataset, and the single entry point for it:
 * `php artisan db:seed --class=QualificaProductionDataSeeder`.
 *
 * Nothing here is fake data — as opposed to every `Demo*` seeder, which
 * fabricates fixtures. These are the real structure, the real hard-coded
 * reference rows and the real legacy catalogues, in the one order their
 * dependencies allow:
 *
 *   1. QualificaTemplateSeeder     — structure only: the custom field
 *                                    definitions per module.
 *   2. QualificaCatalogSeeder      — the hard-coded reference data: sources,
 *                                    reward types, the product category tree
 *                                    with its product attributes, the 252 GOL
 *                                    training courses and the 10 self-funded
 *                                    ones.
 *   3. TestUsersSeeder             — the named tester accounts and the
 *                                    supervisor/commercial/marketing roles.
 *   4. QualificaLegacyImportSeeder — the support tables pulled from the legacy
 *                                    system through the Migrazioni engine.
 *
 * The order is a contract, not a preference:
 *   - step 4 adopts step 2's source catalogue by name instead of duplicating
 *     it, and nests its imported taxonomy under step 2's "Consulenza" root;
 *   - step 4 acts on behalf of a super-admin, which step 3 guarantees exists
 *     (it runs `permissions:sync` and `roles:create-super-admin` itself).
 *
 * Every step stays runnable on its own and is idempotent, so this seeder is
 * too: re-running it converges instead of duplicating. Step 4 is a no-op with
 * a warning when no external system is configured — the rest still lands.
 *
 * Run on its own, step 2 offers to chain step 4 interactively; here it must
 * not, or the import would run twice — hence the explicit `false`.
 */
class QualificaProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(QualificaTemplateSeeder::class);
        // No prompt: step 4 below already runs the import, and the actor it
        // needs only exists after step 3.
        $this->callWith(QualificaCatalogSeeder::class, ['askForLegacyImport' => false]);
        $this->call(TestUsersSeeder::class);
        $this->call(QualificaLegacyImportSeeder::class);
    }
}
