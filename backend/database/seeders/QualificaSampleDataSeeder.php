<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * The FABRICATED commercial pipeline, and the single entry point for it:
 * `php artisan db:seed --class=QualificaSampleDataSeeder`.
 *
 * Split out of QualificaProductionDataSeeder on user directive 2026-09-08:
 * that chain is the client's real structure and reference data, and the fake
 * Anagrafiche / Lead / Opportunita' it used to append as its last two steps
 * did not belong in a production seed. They live here instead, on demand,
 * together with the Gestione Richieste rows the chain never had.
 *
 * The order their dependencies allow:
 *
 *   1. QualificaSampleLeadSeeder     — one project, one campaign, one
 *                                      Anagrafica per lead, and a batch of
 *                                      leads part of which is already
 *                                      converted into an opportunity.
 *   2. QualificaSampleOpportunitySeeder — the other creation path: deals with
 *                                      no lead behind them, on step 1's
 *                                      Anagrafiche.
 *   3. QualificaSampleRequestSeeder  — the Gestione Richieste module: requests
 *                                      created through the module's own write
 *                                      path, each one an Opportunity plus its
 *                                      Offerta, on the Anagrafiche steps 1
 *                                      and 2 left free.
 *
 * The order is a contract, not a preference: steps 2 and 3 both consume step
 * 1's Anagrafiche, and an anagrafica carries ONE open opportunity at a time
 * (user directive 2026-08-31) — so each of the two takes what the previous
 * one left, through the shared PicksFreeRegistries pool.
 *
 * PREREQUISITE, not a dependency this seeder resolves itself: run
 * `QualificaProductionDataSeeder` first. Every step here needs the product
 * category tree with its effective business functions (without one no lead
 * converts, spec 0044 AC-012, and no request has a valid `product_lines`),
 * plus the accounts and the operational sites it seeds. Run on a bare
 * database each step skips itself with a warning rather than half-seeding.
 *
 * Every step stays runnable on its own, and the whole chain ACCUMULATES
 * (user directive 2026-09-08): every run appends a batch instead of
 * converging, so more rows are one more run away.
 *
 * The batch sizes are `run()` parameters with the steps' own defaults, which
 * is what lets the terminal drive them:
 * `php artisan qualifica:seed-sample --leads=200 --requests=50`. Plain
 * `db:seed --class=QualificaSampleDataSeeder` keeps working and takes the
 * defaults — the command is a front-end over this seeder, never a second
 * implementation of it.
 */
class QualificaSampleDataSeeder extends Seeder
{
    public function run(
        int $leads = QualificaSampleLeadSeeder::DEFAULT_LEADS,
        int $convertedLeads = QualificaSampleLeadSeeder::DEFAULT_CONVERTED_LEADS,
        int $opportunities = QualificaSampleOpportunitySeeder::DEFAULT_OPPORTUNITIES,
        int $requests = QualificaSampleRequestSeeder::DEFAULT_REQUESTS,
    ): void {
        $this->callWith(QualificaSampleLeadSeeder::class, [
            'leads' => $leads,
            'convertedLeads' => $convertedLeads,
        ]);
        $this->callWith(QualificaSampleOpportunitySeeder::class, ['opportunities' => $opportunities]);
        $this->callWith(QualificaSampleRequestSeeder::class, ['requests' => $requests]);
    }
}
