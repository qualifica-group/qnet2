<?php

namespace Database\Seeders\Support;

use App\Enums\ProformaRequestKind;
use App\Enums\QuoteLineType;
use App\Enums\ResidualMode;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\ProformaRequest;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\Invoices\InvoiceCollectionService;
use App\Services\Invoices\InvoiceService;
use Carbon\CarbonImmutable;

/**
 * The active-invoicing write path the seeders share (spec 0194): a proforma
 * request on a work order, its document issued through InvoiceService, the
 * collections recorded through InvoiceCollectionService and the registration
 * of the external number. Amounts, numbers and schedules are the real ones.
 */
final class ActiveInvoiceWriter
{
    public const string COLLECT_ALL = 'all';

    public const string COLLECT_FIRST = 'first';

    public const string COLLECT_PARTIAL = 'partial';

    public const string COLLECT_NONE = 'none';

    /** Fallback when neither the quote line nor its product carries a VAT rate. */
    private const string DEFAULT_VAT_RATE = '22';

    /** Days after the partially collected installment when its residual falls due. */
    private const int RESIDUAL_DELAY_DAYS = 30;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly InvoiceCollectionService $collections,
    ) {}

    public function request(WorkOrder $workOrder, User $actor, int $paymentMethodId): ProformaRequest
    {
        return $workOrder->proformaRequests()->create([
            'quote_id' => $workOrder->quote_id,
            'kind' => ProformaRequestKind::Consultancy,
            'payment_method_id' => $paymentMethodId,
            'note' => 'Richiesta proforma demo.',
            'assigned_to' => $actor->id,
            'assigned_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data  the InvoiceService::create payload
     */
    public function issue(WorkOrder $workOrder, User $actor, array $data): Invoice
    {
        return $this->invoices->create($this->request($workOrder, $actor, $data['payment_method_id']), $actor, $data);
    }

    /**
     * The work order's revenue lines in the InvoiceWritePayload line shape.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(WorkOrder $workOrder): array
    {
        $fallbackVatRateId = VatRate::query()->where('rate', self::DEFAULT_VAT_RATE)->value('id')
            ?? VatRate::query()->orderBy('id')->value('id');

        return $workOrder->quoteLines()
            ->where('line_type', QuoteLineType::Revenue->value)
            ->with('product:id,name,vat_rate_id')
            ->get()
            ->map(fn (QuoteLine $line): array => [
                'quote_line_id' => $line->id,
                'product_id' => $line->product_id,
                'description' => $line->additional_description ?? $line->product?->name ?? 'Servizio',
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id ?? $line->product?->vat_rate_id ?? $fallbackVatRateId,
            ])
            ->filter(fn (array $line): bool => $line['vat_rate_id'] !== null && (float) $line['unit_price'] > 0)
            ->values()
            ->all();
    }

    public function collect(Invoice $invoice, string $mode): void
    {
        $installments = $invoice->installments->sortBy('sequence')->values();
        $targets = match ($mode) {
            self::COLLECT_ALL => $installments,
            self::COLLECT_FIRST, self::COLLECT_PARTIAL => $installments->take(1),
            default => collect(),
        };

        foreach ($targets as $installment) {
            $payload = ['collected_amount' => (string) $installment->amount, 'collected_at' => $this->collectedAt($installment)];

            // A partial collection spreads its residual on the later installments (spec 0196, D-5);
            // a single-installment schedule has none, so the residual becomes a new installment.
            if ($mode === self::COLLECT_PARTIAL) {
                $payload['collected_amount'] = bcdiv((string) $installment->amount, '2', 2);
                $payload += $installments->count() > 1
                    ? ['residual_mode' => ResidualMode::Spread->value]
                    : ['residual_mode' => ResidualMode::NewInstallment->value, 'residual_due_date' => $installment->due_date->addDays(self::RESIDUAL_DELAY_DAYS)->toDateString()];
            }

            $this->collections->record($installment, $payload);
        }
    }

    /** Stamps the electronic-invoicing number, which makes the document a registered invoice. */
    public function register(Invoice $invoice, User $actor): void
    {
        $this->invoices->updateDetails($invoice, [
            'external_number' => sprintf('%d/%s', $invoice->number, 'FE'),
            'external_date' => $invoice->document_date->toDateString(),
        ], $actor);
    }

    /** Collected on the due date, never in the future. */
    private function collectedAt(InvoiceInstallment $installment): string
    {
        return $installment->due_date->min(CarbonImmutable::today())->toDateString();
    }
}
