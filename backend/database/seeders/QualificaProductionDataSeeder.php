<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The client's production-like dataset, and the single entry point for it:
 * `php artisan db:seed --class=QualificaProductionDataSeeder`.
 *
 * NOTHING here is fake data — as opposed to every `Demo*` seeder, which
 * fabricates fixtures. These are the real structure, the real hard-coded
 * reference rows and the real legacy catalogues. The fabricated commercial
 * pipeline (Anagrafiche, Lead, Opportunita', Gestione Richieste) used to be
 * appended here as two `*Sample*` steps; the user directive 2026-09-08 moved
 * it out to its own on-demand entry point, `QualificaSampleDataSeeder`, so a
 * production seed produces no demo row at all.
 *
 * The order their dependencies allow:
 *
 *   1. QualificaTemplateSeeder     — the installation's shape: the custom
 *                                    field definitions per module, and the
 *                                    document layout quotes are printed on.
 *   2. QualificaCatalogSeeder      — the hard-coded reference data: sources,
 *                                    reward types, the product category tree
 *                                    with its OFFER attributes and their form
 *                                    sections, the 252 GOL training courses,
 *                                    the 29 DIL ones and the 10 self-funded
 *                                    ones.
 *   3. QualificaTaskTaxonomySeeder — the other block of hard-coded reference
 *                                    data: the four Task classification
 *                                    lookups (tipologia, categoria, priorita',
 *                                    importanza) and the status pick-list.
 *                                    Depends on nothing and nothing depends
 *                                    on it; it sits next to step 2 because it
 *                                    is the same kind of row, not because the
 *                                    order matters.
 *   4. TestUsersSeeder             — the named super-admin account.
 *   5. QualificaLegacyImportSeeder — the support tables pulled from the legacy
 *                                    system through the Migrazioni engine.
 *   6. QualificaBusinessFunctionLinkSeeder — assigns step 2's "Formazione"
 *                                    root and its "APL" subcategory to the
 *                                    business functions step 5 imports.
 *   7. QualificaOperatorSeeder     — the client's real operators (the
 *                                    "Mansionario Operatori"), with their
 *                                    roles, Sedi and product-category
 *                                    competence.
 *   8. QualificaStaffSeeder        — the rest of the client's staff, with the
 *                                    base role (Task and Segnatempo only).
 *                                    Create-only: an account that already
 *                                    exists is never touched.
 *   9. QualificaReportsToSeeder    — who each operator reports to ("Risponde
 *                                    a", spec 0166), from the mansionario's
 *                                    supervision sheet.
 *
 * The order is a contract, not a preference:
 *   - step 5 adopts step 2's source catalogue by name instead of duplicating
 *     it, and nests its imported taxonomy under step 2's "Consulenza" root;
 *   - step 5 acts on behalf of a super-admin, which step 4 guarantees exists
 *     (it runs `permissions:sync` and `roles:create-super-admin` itself);
 *   - step 6 needs BOTH sides: step 2's category and step 5's function. Step 2
 *     already ran it once at its own end (a no-op here, the import had not run
 *     yet), which is why it is repeated — not moved — after step 5;
 *   - step 7 needs the sites step 5 imports and the category functions step 6
 *     links: a competence row carries the category's EFFECTIVE function;
 *   - step 8 runs after step 7, so it finds the named accounts of steps 4
 *     and 7 already there and leaves them alone;
 *   - step 9 needs every account on both sides of a reports-to link: the
 *     operators of step 7 and the staff of step 8.
 *
 * Every step stays runnable on its own and is idempotent, so this seeder is
 * too: re-running it converges instead of duplicating. Step 5 is a no-op with
 * a warning when no external system is configured — the rest still lands.
 *
 * Run on its own, step 2 offers to chain step 5 interactively; here it must
 * not, or the import would run twice — hence the explicit `false`.
 */
class QualificaProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(QualificaTemplateSeeder::class);
        // No prompt: step 5 below already runs the import, and the actor it
        // needs only exists after step 4.
        $this->callWith(QualificaCatalogSeeder::class, ['askForLegacyImport' => false]);
        $this->call(QualificaTaskTaxonomySeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(QualificaLegacyImportSeeder::class);
        $this->call(QualificaBusinessFunctionLinkSeeder::class);
        $this->call(QualificaOperatorSeeder::class);
        $this->call(QualificaStaffSeeder::class);
        $this->call(QualificaReportsToSeeder::class);
    }
}
