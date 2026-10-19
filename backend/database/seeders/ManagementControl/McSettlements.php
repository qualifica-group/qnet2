<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\CommissionSettlementStatus;
use App\Models\CommissionSettlement;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\VatRate;
use App\Services\Commissions\Settlements\CommissionSettlementService;
use App\Services\Commissions\Settlements\SettlementApprovalService;
use App\Services\Commissions\Settlements\SettlementDocumentService;
use App\Services\Commissions\Settlements\SettlementRunData;
use App\Services\Commissions\Settlements\SettlementRunner;
use App\Services\PurchaseInvoices\PurchaseInvoicePaymentService;
use Carbon\CarbonImmutable;

/**
 * The agents' commissions settled every quarter (specs 0212/0213/0267): the run on
 * the quarter a few days after its end takes the commissions accrued on the
 * collections, every positive draft is issued, the agent sends the document two
 * weeks later, the statement is approved (which books the agent's payable invoice)
 * and paid on its due date. The last quarters stay at the step real time reached.
 */
final class McSettlements
{
    private const int RUN_AFTER_DAYS = 5;

    private const int DOCUMENT_AFTER_DAYS = 15;

    private const int APPROVE_AFTER_DAYS = 22;

    private const string COST_TYPE = 'Consulenze professionali';

    public function __construct(
        private readonly SettlementRunner $runner,
        private readonly CommissionSettlementService $settlements,
        private readonly SettlementDocumentService $documents,
        private readonly SettlementApprovalService $approvals,
        private readonly PurchaseInvoicePaymentService $payments,
    ) {}

    public function schedule(McTimeline $timeline, McContext $context): void
    {
        for ($quarter = CarbonImmutable::parse(McCatalogue::PERIOD_START); $quarter->toDateString() <= McCatalogue::PERIOD_END; $quarter = $quarter->addMonths(3)) {
            $from = $quarter->toDateString();
            $to = $quarter->addMonths(3)->subDay();
            $runOn = McTimeline::nextWorkingDay($to->addDays(self::RUN_AFTER_DAYS));

            $timeline->at($runOn->toDateString(), fn () => $this->run($timeline, $context, $from, $to->toDateString(), $runOn));
        }
    }

    private function run(McTimeline $timeline, McContext $context, string $from, string $to, CarbonImmutable $runOn): void
    {
        $view = $this->runner->handle($context->actor, new SettlementRunData($from, $to));
        $drafts = CommissionSettlement::query()->where('commission_settlement_run_id', $view->run->id)
            ->where('status', CommissionSettlementStatus::Draft)->where('taxable_amount', '>', 0)->orderBy('id')->get();

        foreach ($drafts as $draft) {
            $this->settlements->issue($draft, $context->actor);

            $timeline->at(McTimeline::nextWorkingDay($runOn->addDays(self::DOCUMENT_AFTER_DAYS))->toDateString(), fn (string $date) => $this->documents->register(
                $draft->refresh(), $this->documentOf($draft, $date), $context->actor,
            ));
            $timeline->at(McTimeline::nextWorkingDay($runOn->addDays(self::APPROVE_AFTER_DAYS))->toDateString(), fn () => $this->approve($timeline, $context, $draft));
        }
    }

    /**
     * The agent's document: the same amounts as the statement.
     *
     * @return array<string, string>
     */
    private function documentOf(CommissionSettlement $settlement, string $date): array
    {
        return [
            'document_number' => sprintf('AG-%d-%d', $settlement->id, CarbonImmutable::parse($date)->year),
            'document_date' => $date,
            'document_taxable_amount' => (string) $settlement->taxable_amount,
            'document_vat_amount' => (string) $settlement->vat_amount,
            'document_withholding_amount' => (string) $settlement->withholding_amount,
            'document_enasarco_amount' => (string) $settlement->enasarco_agent_amount,
            'document_total_amount' => bcadd((string) $settlement->taxable_amount, (string) $settlement->vat_amount, 2),
        ];
    }

    private function approve(McTimeline $timeline, McContext $context, CommissionSettlement $settlement): void
    {
        $settlement->refresh();
        if ($settlement->status !== CommissionSettlementStatus::DocumentReceived) {
            return;
        }

        $this->approvals->approve($settlement, [
            'payment_method_id' => $context->supplierPaymentMethodId,
            'cost_type_id' => $context->costTypes[self::COST_TYPE],
            'vat_rate_id' => VatRate::query()->where('rate', $settlement->vat_rate ?? '0.00')->orderBy('id')->value('id') ?? $context->vatRateId,
        ], $context->actor);

        $installments = PurchaseInvoiceInstallment::query()->where('purchase_invoice_id', $settlement->refresh()->purchase_invoice_id)->get();
        foreach ($installments as $installment) {
            $timeline->at(McTimeline::nextWorkingDay($installment->due_date->toImmutable())->toDateString(), fn (string $on) => $this->payments->record($installment->refresh(), [
                'paid_amount' => (string) $installment->amount,
                'paid_at' => $on,
            ]));
        }
    }
}
