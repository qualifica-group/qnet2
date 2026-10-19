<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\Concerns\ResolvesSeedActor;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Database\Seeders\ManagementControl\McCatalogue;
use Database\Seeders\ManagementControl\McDealPlanner;
use Database\Seeders\ManagementControl\McDirectory;
use Database\Seeders\ManagementControl\McPayables;
use Database\Seeders\ManagementControl\McPayroll;
use Database\Seeders\ManagementControl\McPurchaseRequests;
use Database\Seeders\ManagementControl\McSales;
use Database\Seeders\ManagementControl\McSettlements;
use Database\Seeders\ManagementControl\McTimeline;
use Database\Seeders\ManagementControl\McTimesheet;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The management control dataset (controllo di gestione): January 2025 to October
 * 2026 of a training and consulting company, Italian data, through the real services
 * with the clock moved onto each simulated day (McTimeline):
 *
 *   1. master data: customers, suppliers, agents with fiscal profile, commission rules,
 *      cost types (McDirectory); the staff with salary history (McPayroll);
 *   2. the sales cycle of ~400 deals: opportunity, offer with budgeted costs, outcome,
 *      contract, validation, commessa (McSales);
 *   3. the delivery of every line: active invoice, registration, collections; orders
 *      and supplier invoices; commessa costs; the team's hours (McDelivery);
 *   4. the structure costs, the quarterly purchase requests and the agent settlements;
 *   5. the full segnatempo: commessa hours plus the generic hours of every working day,
 *      charged by the management control to the person's functions (McTimesheet).
 *
 * Nothing is dated after the real today: what falls later stays open (pipeline,
 * installments due, backlog). Run by DemoDataSeeder after the other demo modules,
 * whose wipe of the downstream tables makes it re-runnable; the master data and the
 * rules are found again by name.
 */
class DemoManagementControlSeeder extends Seeder
{
    use ResolvesSeedActor;
    use SeedsWithoutMail;

    public function run(): void
    {
        $actor = $this->resolveActor();
        if ($actor === null || ! Product::query()->where('name', McCatalogue::COST_TEACHER)->exists()) {
            $this->command?->warn('Management control dataset skipped: no user or no demo catalogue (run DemoProductSeeder first).');

            return;
        }

        Auth::setUser($actor);

        try {
            $this->withoutMail(fn () => $this->seed($actor));
        } finally {
            Auth::forgetUser();
        }
    }

    private function seed(User $actor): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(McCatalogue::FAKER_SEED);
        $timesheet = new McTimesheet($faker);
        $this->container->instance(McTimesheet::class, $timesheet);
        $this->container->instance(McPayables::class, $this->container->make(McPayables::class));

        // Step 1: master data and staff
        $directory = $this->container->make(McDirectory::class);
        $companies = $directory->sellingCompanies();
        $roster = $this->container->make(McPayroll::class)->handle($faker, $companies);
        $this->clearGenericTimeEntries(array_column($roster, 'user_id'));
        $deliveryIds = array_column(array_filter($roster, fn (array $person): bool => $person['delivery']), 'user_id');
        $context = $directory->build($faker, $actor, $companies, $deliveryIds);

        // Step 2: every dated event of the period on the timeline
        $timeline = new McTimeline;
        $deals = $this->container->make(McDealPlanner::class)->plan($faker, array_map(fn (Registry $customer): int => $customer->id, $context->customers));
        $sales = $this->container->make(McSales::class);
        foreach ($deals as $number => $deal) {
            $sales->schedule($timeline, $context, $faker, $deal, $number + 1);
        }
        $this->container->make(McPayables::class)->scheduleStructure($timeline, $context, $faker);
        $this->container->make(McPurchaseRequests::class)->schedule($timeline, $context, $faker);
        $this->container->make(McSettlements::class)->schedule($timeline, $context);

        // Step 3: run the period day by day, then write the segnatempo
        $events = $timeline->run();
        $entries = $timesheet->flush($roster);

        $this->command?->info(sprintf('Management control dataset: %d deals planned, %d events run, %d time entries.', count($deals), $events, $entries));
    }

    /**
     * The roster's segnatempo of the period with no task is rebuilt: the other demo seeders' hours would
     * otherwise double the days the dataset fills.
     *
     * @param  list<int>  $userIds
     */
    private function clearGenericTimeEntries(array $userIds): void
    {
        DB::table('time_entries')->whereIn('user_id', $userIds)->whereNull('task_id')
            ->whereBetween('date', [McCatalogue::PERIOD_START, McCatalogue::PERIOD_END])->delete();
    }
}
