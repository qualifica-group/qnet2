<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\ProformaRequestKind;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Services\Invoices\InvoiceCollectionService;
use App\Services\Invoices\InvoiceService;
use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * The active cycle of a delivered line: the proforma request and its document on the
 * invoice day (installments planned by the payment method), the registration as an
 * electronic invoice a few days later, and the collection of every installment around
 * its due date. Late payers collect months late or never, so the receivables age.
 */
final class McReceivables
{
    private const array REGISTER_DELAY_DAYS = [1, 6];

    private const array ON_TIME_DELAY_DAYS = [0, 20];

    private const array LATE_DELAY_DAYS = [35, 160];

    private const int LATE_NEVER_PERCENT = 35;

    private const string REQUEST_NOTE = 'Fatturazione a consegna dell\'attivita.';

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceCollectionService $collections,
    ) {}

    public function issue(McTimeline $timeline, McContext $context, Generator $faker, WorkOrder $workOrder, QuoteLine $quoteLine, string $date): void
    {
        $quote = $workOrder->quote()->with('opportunity:id,registry_id')->first();
        $companyId = (int) $quote->company_id;

        // Step 1: the proforma request on the commessa, then its document
        $request = $workOrder->proformaRequests()->create([
            'quote_id' => $quote->id,
            'kind' => ProformaRequestKind::Consultancy,
            'payment_method_id' => $quote->payment_method_id,
            'note' => self::REQUEST_NOTE,
            'assigned_to' => $context->actor->id,
            'assigned_by' => $context->actor->id,
        ]);

        $invoice = $this->invoices->create($request, $context->actor, [
            'document_date' => $date,
            'company_id' => $companyId,
            'customer_registry_id' => $quote->opportunity->registry_id,
            'payment_method_id' => $quote->payment_method_id,
            'financial_account_id' => $context->bankOf($companyId),
            'notes' => null,
            'lines' => [[
                'quote_line_id' => $quoteLine->id,
                'product_id' => $quoteLine->product_id,
                'description' => $quoteLine->additional_description ?? $workOrder->title,
                'quantity' => (string) $quoteLine->quantity,
                'unit_price' => (string) $quoteLine->unit_price,
                'vat_rate_id' => $quoteLine->vat_rate_id ?? $context->vatRateId,
            ]],
        ]);

        // Step 2: registration and collections
        $registerOn = McTimeline::nextWorkingDay(CarbonImmutable::parse($date)->addDays($faker->numberBetween(...self::REGISTER_DELAY_DAYS)))->toDateString();
        $timeline->at($registerOn, fn (string $on) => $this->register($invoice, $on, $context));
        $this->scheduleCollections($timeline, $faker, $invoice, $context->latePayers[$quote->opportunity->registry_id] ?? false);
    }

    private function register(Invoice $invoice, string $date, McContext $context): void
    {
        $invoice->refresh();
        $this->invoices->updateDetails($invoice, [
            'external_number' => sprintf('FE-%d-%05d', $invoice->year ?? $invoice->document_date->year, $invoice->number),
            'external_date' => $date,
        ], $context->actor);
    }

    private function scheduleCollections(McTimeline $timeline, Generator $faker, Invoice $invoice, bool $latePayer): void
    {
        if ($latePayer && $faker->boolean(self::LATE_NEVER_PERCENT)) {
            return;
        }

        $invoice->installments()->get()->each(function (InvoiceInstallment $installment) use ($timeline, $faker, $latePayer): void {
            $delay = $faker->numberBetween(...($latePayer ? self::LATE_DELAY_DAYS : self::ON_TIME_DELAY_DAYS));
            $collectOn = McTimeline::nextWorkingDay($installment->due_date->toImmutable()->addDays($delay))->toDateString();

            $timeline->at($collectOn, fn (string $on) => $this->collections->record($installment->refresh(), [
                'collected_amount' => (string) $installment->amount,
                'collected_at' => $on,
            ]));
        });
    }
}
