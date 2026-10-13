<?php

namespace Database\Seeders;

use App\Models\AgentFiscalProfile;
use App\Models\EnasarcoParameter;
use App\Models\User;
use Database\Seeders\Concerns\SeedsWithoutMail;
use Illuminate\Database\Seeder;

/**
 * Purchasing and accounting sample data ADDED to an existing database, never
 * cleared: `php artisan db:seed --class=DemoAccountingSampleSeeder`. Unlike
 * DemoDataSeeder (which wipes and rebuilds the whole demo graph) every run
 * appends a batch on top of what is there, through the modules' own services,
 * acting as the first super-admin:
 *
 *   1. RDA with lines in every status;
 *   2. ODA from half the approved RDA lines plus direct ones, in every state;
 *   3. payable documents on the billable orders, with schedules and payments;
 *   4. proforma requests and active invoices on the work orders nothing
 *      invoices yet, with collections;
 *   5. the commission ledger re-synced on the collected invoices and a
 *      settlement run on last month. The ENASARCO parameters and the agent
 *      profiles are seeded only when the tables are empty, never overwritten.
 *
 * Kept out of DemoDataSeeder on purpose: that path already seeds the same
 * modules through its own Demo*Seeder steps.
 */
class DemoAccountingSampleSeeder extends Seeder
{
    use SeedsWithoutMail;

    private const string ACTOR_ROLE = 'super-admin';

    public function run(
        int $purchaseRequests = DemoSamplePurchaseRequestSeeder::DEFAULT_REQUESTS,
        int $purchaseOrders = DemoSamplePurchaseOrderSeeder::DEFAULT_ORDERS,
        int $workOrders = DemoSampleInvoiceSeeder::DEFAULT_WORK_ORDERS,
    ): void {
        // whereHas, not the role() scope: the scope throws on a database without the role
        $actor = User::query()->whereHas('roles', fn ($query) => $query->where('name', self::ACTOR_ROLE))->orderBy('id')->first();

        if ($actor === null) {
            $this->command?->warn('Accounting sample skipped: no super-admin user to act as.');

            return;
        }

        $this->withoutMail(fn () => $this->seedChain($actor, $purchaseRequests, $purchaseOrders, $workOrders));
    }

    private function seedChain(User $actor, int $purchaseRequests, int $purchaseOrders, int $workOrders): void
    {
        // Step 0: the agent commission rules and their showcase commesse, so step 4 invoices
        // work orders that carry commissions (idempotent, never touches other offers)
        $this->call(DemoCommissionShowcaseSeeder::class);

        // Step 1-2: purchasing
        $this->callWith(DemoSamplePurchaseRequestSeeder::class, ['actor' => $actor, 'requests' => $purchaseRequests]);
        $this->callWith(DemoSamplePurchaseOrderSeeder::class, ['actor' => $actor, 'orders' => $purchaseOrders]);

        // Step 3-4: payable and receivable documents
        $this->callWith(DemoSamplePurchaseInvoiceSeeder::class, ['actor' => $actor]);
        $this->callWith(DemoSampleInvoiceSeeder::class, ['actor' => $actor, 'workOrders' => $workOrders]);

        // Step 5: commissions, bootstrapping only what is still empty
        $this->call(DemoCommissionAccrualSeeder::class);

        if (! EnasarcoParameter::query()->exists()) {
            $this->call(DemoEnasarcoParameterSeeder::class);
        }

        if (! AgentFiscalProfile::query()->exists()) {
            $this->call(DemoAgentFiscalProfileSeeder::class);
        }

        $this->call(DemoCommissionSettlementSeeder::class);
    }
}
