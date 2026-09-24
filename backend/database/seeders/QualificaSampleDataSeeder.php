<?php

namespace Database\Seeders;

use App\Models\Opportunity;
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
 *   4. QualificaSampleQuoteSeeder    — an Offerta on each opportunity of steps
 *                                      1 and 2, spread over its working
 *                                      statuses (user directive 2026-09-24).
 *   5. QualificaSampleContractSeeder — some offers of steps 3 and 4 closed as
 *                                      won, so the system opens their
 *                                      Contratto, walked onto its lifecycle.
 *   6. QualificaSampleWorkOrderSeeder — "Programma" on step 5's validated
 *                                      contracts: one Commessa each.
 *   7. QualificaSampleTaskSeeder     — Task on the batch's Commesse and
 *                                      Opportunita'; the completed ones carry
 *                                      the segnatempo the action writes.
 *   8. QualificaSampleTimeEntrySeeder — day-to-day Segnatempo on the open
 *                                      Tasks, Commesse and Opportunita'.
 *
 * The order is a contract, not a preference: steps 2 and 3 both consume step
 * 1's Anagrafiche, and an anagrafica carries ONE open opportunity at a time
 * (user directive 2026-08-31) — so each of the two takes what the previous
 * one left, through the shared PicksFreeRegistries pool. Steps 4 to 6 follow
 * the system's own chain Offerta -> Contratto -> Commessa, each one feeding on
 * what the previous one produced; steps 7 and 8 hang on all of it. Steps 4
 * to 8 are confined to THIS run's opportunities through the id watermark
 * taken before step 1 — a real offer on the same database is never closed,
 * contracted, programmed or worked on by a seed.
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
 * `php artisan qualifica:seed-sample --leads=200 --requests=50 --contracts=20`. Plain
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
        int $quotes = QualificaSampleQuoteSeeder::DEFAULT_QUOTES,
        int $contracts = QualificaSampleContractSeeder::DEFAULT_CONTRACTS,
        int $workOrders = QualificaSampleWorkOrderSeeder::DEFAULT_WORK_ORDERS,
        int $tasks = QualificaSampleTaskSeeder::DEFAULT_TASKS,
        int $timeEntries = QualificaSampleTimeEntrySeeder::DEFAULT_TIME_ENTRIES,
    ): void {
        $sinceOpportunityId = (int) Opportunity::query()->max('id');

        $this->callWith(QualificaSampleLeadSeeder::class, [
            'leads' => $leads,
            'convertedLeads' => $convertedLeads,
        ]);
        $this->callWith(QualificaSampleOpportunitySeeder::class, ['opportunities' => $opportunities]);
        $this->callWith(QualificaSampleRequestSeeder::class, ['requests' => $requests]);
        $this->callWith(QualificaSampleQuoteSeeder::class, ['quotes' => $quotes, 'sinceOpportunityId' => $sinceOpportunityId]);
        $this->callWith(QualificaSampleContractSeeder::class, ['contracts' => $contracts, 'sinceOpportunityId' => $sinceOpportunityId]);
        $this->callWith(QualificaSampleWorkOrderSeeder::class, ['workOrders' => $workOrders, 'sinceOpportunityId' => $sinceOpportunityId]);
        $this->callWith(QualificaSampleTaskSeeder::class, ['tasks' => $tasks, 'sinceOpportunityId' => $sinceOpportunityId]);
        $this->callWith(QualificaSampleTimeEntrySeeder::class, ['timeEntries' => $timeEntries, 'sinceOpportunityId' => $sinceOpportunityId]);
    }
}
