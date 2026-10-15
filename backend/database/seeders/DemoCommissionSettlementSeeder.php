<?php

namespace Database\Seeders;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\CommissionSettlementRun;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\Registry;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Commissions\Settlements\CommissionSettlementService;
use App\Services\Commissions\Settlements\SettlementApprovalService;
use App\Services\Commissions\Settlements\SettlementDocumentService;
use App\Services\Commissions\Settlements\SettlementRunData;
use App\Services\Commissions\Settlements\SettlementRunner;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Illuminate\Database\Seeder;

/**
 * Demo agent settlements (spec 0213, 0267): one run on the previous month through the
 * real runner, then every other draft is issued through the real service; of the issued
 * ones the first receives its agent document and the second is approved too (its agent
 * gets a supplier registry, the payable invoice comes from the real approval service).
 * Depends on DemoCommissionAccrualSeeder, DemoAgentFiscalProfileSeeder and
 * DemoEnasarcoParameterSeeder. Re-runnable: a run on the same period is not repeated and
 * the document/approval step runs once.
 */
class DemoCommissionSettlementSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    public function __construct(
        private readonly SettlementRunner $runner,
        private readonly CommissionSettlementService $settlements,
        private readonly SettlementDocumentService $documents,
        private readonly SettlementApprovalService $approvals,
    ) {}

    public function run(): void
    {
        $lastMonth = CarbonImmutable::today()->subMonthNoOverflow();
        $from = $lastMonth->startOfMonth()->toDateString();
        $to = $lastMonth->endOfMonth()->toDateString();

        // Step 1: nothing to do without the demo admin
        $alreadyRun = CommissionSettlementRun::query()
            ->whereDate('period_from', $from)->whereDate('period_to', $to)->exists();
        $actor = User::query()->where('email', self::DEMO_EMAIL)->first();

        if ($actor === null) {
            return;
        }

        if (! $alreadyRun) {
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

        // Step 4: the agent document and the approval, once
        $this->advanceIssued($actor);
    }

    /**
     * The first issued settlement receives its document, the second is approved as well.
     */
    private function advanceIssued(User $actor): void
    {
        $advanced = CommissionSettlement::query()
            ->whereIn('status', [CommissionSettlementStatus::DocumentReceived, CommissionSettlementStatus::Approved])->exists();

        if ($advanced) {
            return;
        }

        $issued = CommissionSettlement::query()->where('status', CommissionSettlementStatus::Issued)->orderBy('id')->limit(2)->get();

        foreach ($issued->values() as $position => $settlement) {
            $received = $this->documents->register($settlement, $this->documentOf($settlement, $position), $actor);

            if ($position === 1) {
                $this->approve($received, $actor);
            }
        }
    }

    /**
     * The document the agent would send: the same amounts as the settlement.
     *
     * @return array<string, string>
     */
    private function documentOf(CommissionSettlement $settlement, int $position): array
    {
        return [
            'document_number' => 'DEMO-AG-'.($position + 1),
            'document_date' => now()->toDateString(),
            'document_taxable_amount' => (string) $settlement->taxable_amount,
            'document_vat_amount' => (string) $settlement->vat_amount,
            'document_withholding_amount' => (string) $settlement->withholding_amount,
            'document_enasarco_amount' => (string) $settlement->enasarco_agent_amount,
            'document_total_amount' => bcadd((string) $settlement->taxable_amount, (string) $settlement->vat_amount, 2),
        ];
    }

    /**
     * Approves when the lookups it needs exist (payment method, cost type, the VAT rate of the settlement).
     */
    private function approve(CommissionSettlement $settlement, User $actor): void
    {
        $profile = $settlement->agentFiscalProfile()->first();
        $paymentMethodId = $profile?->payment_method_id ?? PaymentMethod::query()->where('is_active', true)->orderBy('id')->value('id');
        $costTypeId = $profile?->cost_type_id ?? CostType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->value('id');
        $vatRateId = VatRate::query()->where('rate', $settlement->vat_rate ?? '0.00')->orderBy('id')->value('id');

        if ($paymentMethodId === null || $costTypeId === null || $vatRateId === null) {
            return;
        }

        $referent = $settlement->referent()->first();

        if ($referent->supplier_registry_id === null) {
            $referent->update(['supplier_registry_id' => Registry::factory()->supplier()->create(['name' => $referent->name])->id]);
        }

        $this->approvals->approve($settlement, ['payment_method_id' => $paymentMethodId, 'cost_type_id' => $costTypeId, 'vat_rate_id' => $vatRateId], $actor);
    }
}
