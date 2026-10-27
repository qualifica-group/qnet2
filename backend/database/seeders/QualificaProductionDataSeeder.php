<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsWithoutMail;
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
 *                                    reward types, the two product typologies
 *                                    ("Ente", "Consulenza", ProductTypologySeeder:
 *                                    the SOA products and the imported ones
 *                                    are filed under them), the product category tree
 *                                    with its OFFER attributes and their form
 *                                    sections, the 252 GOL training courses,
 *                                    the 29 DIL ones and the 10 self-funded
 *                                    ones, and the business functions its
 *                                    categories are linked to
 *                                    (QualificaBusinessFunctionLinkSeeder).
 *      QualificaCostProductSeeder  — the cost-only products (note spese,
 *                                    articoli) under the active "Costi"
 *                                    branch: the legacy
 *                                    cost items as NEW products, separate
 *                                    from step 5's "[OLD] Costi" history.
 *   3. QualificaTaskTaxonomySeeder — the other block of hard-coded reference
 *                                    data: the four Task classification
 *                                    lookups (tipologia, categoria, priorita',
 *                                    importanza) and the status pick-list.
 *                                    Depends on nothing and nothing depends
 *                                    on it; it sits next to step 2 because it
 *                                    is the same kind of row, not because the
 *                                    order matters.
 *      QualificaSectorSeeder       — the 44 EA sectors, active, with their
 *                                    code (spec 0213). Same kind of row, same
 *                                    reasoning; step 5 deactivates only the
 *                                    legacy sectors (`old_id`), never these.
 *      QualificaAbsenceTypeSeeder  — the absence type catalogue (Ferie,
 *                                    Permesso, Malattia, spec 0278). Same kind
 *                                    of row, same reasoning.
 *      QualificaContractTypeSeeder — the contract type catalogue (Tempo
 *                                    determinato, Tempo indeterminato,
 *                                    Apprendistato, spec 0290/0291).
 *                                    Same kind of row, same reasoning.
 *      QualificaWorkspaceSeeder    — the three menu apps (Contabilita', HR,
 *                                    Sales, spec 0317), each with the pages
 *                                    of its job. Create-only: an app the
 *                                    super-admin already edited is left alone.
 *   4. TestUsersSeeder             — the named super-admin account.
 *      WorkOrderPaymentStatusSeeder — the legacy payment statuses of a
 *                                    commessa line.
 *   5. QualificaLegacyImportSeeder — the support tables pulled from the legacy
 *                                    system through the Migrazioni engine,
 *                                    the sellable products included, the
 *                                    legacy catalogue under "Old category".
 *   6. QualificaOperatorSeeder     — the client's real operators (the
 *                                    "Mansionario Operatori"), with their
 *                                    roles, Sedi and product-category
 *                                    competence.
 *   7. QualificaStaffSeeder        — the rest of the client's staff, with the
 *                                    base role (Task and Segnatempo only).
 *                                    Create-only: an account that already
 *                                    exists is never touched.
 *      QualificaHrOfficeSeeder     — the "Risorse Umane" role (spec 0283),
 *                                    added to the people of the HR function
 *                                    on top of the role of step 6 or 7.
 *      QualificaCommercialBackOfficeSeeder — the "Back Office Commerciale"
 *                                    role (spec 0299), added the same way to
 *                                    six listed people (spec 0299 D-6).
 *      QualificaStockKeeperSeeder  — the "Gestione Dotazioni" role (spec
 *                                    0307), added to one listed person.
 *   8. QualificaReportsToSeeder    — who each operator reports to ("Risponde
 *                                    a", spec 0166), from the mansionario's
 *                                    supervision sheet.
 *   9. QualificaHrCatalogSeeder    — the job activities and job roles
 *                                    (Mansioni, spec 0271) of the client's
 *                                    "Pianta Organica", merged, each role
 *                                    linked to its activities, then each
 *                                    listed person's roles and shared
 *                                    mailboxes (OrgChartRoster).
 *  10. QualificaLegacyImportSeeder — again, for the legacy users only
 *                                    (USER_SOURCES): each one is matched to
 *                                    the account steps 4, 6 and 7 created and
 *                                    gets its `old_id`; the unmatched ones are
 *                                    created from the legacy record.
 *
 * The order is a contract, not a preference:
 *   - step 5 adopts step 2's sources and business functions by name instead
 *     of duplicating them;
 *   - step 5 acts on behalf of a super-admin, which step 4 guarantees exists
 *     (it runs `permissions:sync` and `roles:create-super-admin` itself);
 *   - step 5 resolves each product's typology BY NAME (ProductsSource), so
 *     the typologies land before it: without "Consulenza" every consultancy
 *     product would fall back on the default typology with a warning;
 *   - step 6 needs the sites step 5 imports and the category functions step 2
 *     links: a competence row carries the category's EFFECTIVE function;
 *   - step 7 runs after step 6, so it finds the named accounts of steps 4
 *     and 6 already there and leaves them alone; the HR office role lands
 *     once both have created their accounts;
 *   - step 8 needs every account on both sides of a reports-to link: the
 *     operators of step 6 and the staff of step 7.
 *   - step 9 assigns roles and mailboxes to the accounts of steps 4, 6 and 7, so
 *     it runs once they all exist.
 *   - step 10 matches the legacy users to the accounts of steps 4, 6 and 7:
 *     run before them, it would create the accounts itself and the seeders
 *     would land on those.
 *
 * Every step stays runnable on its own and is idempotent, so this seeder is
 * too: re-running it converges instead of duplicating. Steps 5 and 10 are
 * no-ops with a warning when no external system is configured — the rest
 * still lands.
 *
 * Run on its own, step 2 offers to chain step 5 interactively; here it must
 * not, or the import would run twice — hence the explicit `false`.
 */
class QualificaProductionDataSeeder extends Seeder
{
    use SeedsWithoutMail;

    public function run(): void
    {
        // No email leaves the import: the write paths it goes through still
        // leave their in-app notifications.
        $this->withoutMail($this->seedProductionData(...));
    }

    private function seedProductionData(): void
    {
        $this->call(QualificaTemplateSeeder::class);
        // No prompt: step 5 below already runs the import, and the actor it
        // needs only exists after step 4.
        $this->callWith(QualificaCatalogSeeder::class, ['askForLegacyImport' => false]);
        $this->call(QualificaCostProductSeeder::class);
        $this->call(QualificaTaskTaxonomySeeder::class);
        $this->call(QualificaSectorSeeder::class);
        $this->call(QualificaAbsenceTypeSeeder::class);
        $this->call(QualificaContractTypeSeeder::class);
        $this->call(QualificaWorkspaceSeeder::class);
        $this->call(TestUsersSeeder::class);
        $this->call(WorkOrderPaymentStatusSeeder::class);
        $this->callWith(QualificaLegacyImportSeeder::class, ['sources' => QualificaLegacyImportSeeder::SOURCES]);
        $this->call(QualificaOperatorSeeder::class);
        $this->call(QualificaStaffSeeder::class);
        $this->call(QualificaHrOfficeSeeder::class);
        $this->call(QualificaCommercialBackOfficeSeeder::class);
        $this->call(QualificaStockKeeperSeeder::class);
        $this->call(QualificaReportsToSeeder::class);
        $this->call(QualificaHrCatalogSeeder::class);
        $this->callWith(QualificaLegacyImportSeeder::class, ['sources' => QualificaLegacyImportSeeder::USER_SOURCES]);
    }
}
