<?php

namespace Database\Seeders;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\User;
use App\Services\Commissions\Settlements\CommissionSettlementService;
use App\Services\Commissions\Settlements\SettlementRunData;
use App\Services\Commissions\Settlements\SettlementRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Illuminate\Database\Seeder;

/**
 * Demo agent settlements (spec 0213): one run on the previous month through the
 * real runner, then every other draft is issued through the real service.
 * Depends on DemoCommissionAccrualSeeder, DemoAgentFiscalProfileSeeder and
 * DemoEnasarcoParameterSeeder. Re-runnable: a run on the same period is not repeated.
 */
class DemoCommissionSettlementSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function __construct(
        private readonly SettlementRunner $runner,
        private readonly CommissionSettlementService $settlements,
    ) {}

    public function run(): void
    {
        $lastMonth = CarbonImmutable::today()->subMonthNoOverflow();
        $from = $lastMonth->startOfMonth()->toDateString();
        $to = $lastMonth->endOfMonth()->toDateString();

        // Step 1: nothing to do when the period already has a run
        $alreadyRun = CommissionSettlementRun::query()
            ->whereDate('period_from', $from)->whereDate('period_to', $to)->exists();
        $actor = User::query()->where('email', self::DEMO_EMAIL)->first();

        if ($alreadyRun || $actor === null) {
            return;
        }

        // Step 2: launch the run as the demo admin
        $view = $this->runner->handle($actor, new SettlementRunData($from, $to));

        // Step 3: issue every other draft, leaving the rest as drafts
        $drafts = CommissionSettlement::query()
            ->where('commission_settlement_run_id', $view->run->id)
            ->where('status', CommissionSettlementStatus::Draft)
            ->where('taxable_amount', '>', 0)
            ->orderBy('id')->get();

        foreach ($drafts->values() as $position => $draft) {
            if ($position % 2 === 0) {
                $this->settlements->issue($draft, $actor);
            }
        }
    }
}
