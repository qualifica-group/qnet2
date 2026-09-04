<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The client's production-like dataset, and the single entry point for it:
 * `php artisan db:seed --class=QualificaProductionDataSeeder`.
 *
 * Steps 1 to 7 are not fake data — as opposed to every `Demo*` seeder, which
 * fabricates fixtures. They are the real structure, the real hard-coded
 * reference rows and the real legacy catalogues. Steps 8 and 9 (`*Sample*`)
 * are the one deliberate exception, added on user directive 2026-07-31: a
 * fabricated commercial pipeline, so the Lead and Opportunity grids are not
 * empty on a fresh install.
 *
 * The order their dependencies allow:
 *
 *   1. QualificaTemplateSeeder     — the installation's shape: the custom
 *                                    field definitions per module, and the
 *                                    document layout quotes are printed on.
 *   2. QualificaCatalogSeeder      — the hard-coded reference data: sources,
 *                                    reward types, the product category tree
 *                                    with its product attributes, the 252 GOL
 *                                    training courses and the 10 self-funded
 *                                    ones.
 *   3. QualificaTaskTaxonomySeeder — the other block of hard-coded reference
 *                                    data: the four Task classification
 *                                    lookups (tipologia, categoria, priorita',
 *                                    importanza) and the status pick-list.
 *                                    Depends on nothing and nothing depends
 *                                    on it; it sits next to step 2 because it
 *                                    is the same kind of row, not because the
 *                                    order matters.
 *   4. TestUsersSeeder             — the named tester accounts and the
 *                                    supervisor/commercial/marketing roles.
 *   5. QualificaLegacyImportSeeder — the support tables pulled from the legacy
 *                                    system through the Migrazioni engine.
 *   6. QualificaBusinessFunctionLinkSeeder — assigns step 2's "Formazione"
 *                                    root to the business function step 5
 *                                    imports.
 *   7. QualificaOperatorSiteLinkSeeder — gives step 4's accounts the
 *                                    operational site step 5 imports, so they
 *                                    are selectable as operators.
 *   8. QualificaSampleLeadSeeder   — the sample pipeline: one project, one
 *                                    campaign, one Anagrafica per lead, and a
 *                                    batch of leads part of which is already
 *                                    converted into an opportunity.
 *   9. QualificaSampleOpportunitySeeder — the other creation path: deals with
 *                                    no lead behind them, on step 8's
 *                                    Anagrafiche.
 *
 * The order is a contract, not a preference:
 *   - step 5 adopts step 2's source catalogue by name instead of duplicating
 *     it, and nests its imported taxonomy under step 2's "Consulenza" root;
 *   - step 5 acts on behalf of a super-admin, which step 4 guarantees exists
 *     (it runs `permissions:sync` and `roles:create-super-admin` itself);
 *   - step 6 needs BOTH sides: step 2's category and step 5's function. Step 2
 *     already ran it once at its own end (a no-op here, the import had not run
 *     yet), which is why it is repeated — not moved — after step 5;
 *   - step 7 needs both sides too: step 4's accounts and step 5's sites. It is
 *     a separate step rather than part of step 4 for exactly that reason —
 *     step 4 has to precede the import, the sites only exist after it;
 *   - step 8 needs step 2's product category tree (the conversion derives the
 *     opportunity's product line from the campaign's business function and
 *     category, spec 0044 AC-012) and takes step 5's operational sites and
 *     step 4's accounts as the leads' sede/operatore;
 *   - step 9 reuses step 8's Anagrafiche instead of seeding a second set, so
 *     it comes last.
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
        $this->call(QualificaOperatorSiteLinkSeeder::class);
        $this->call(QualificaSampleLeadSeeder::class);
        $this->call(QualificaSampleOpportunitySeeder::class);
    }
}
