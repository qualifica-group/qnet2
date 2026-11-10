<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Targets;

use App\Migrations\MigrationRegistry;
use App\Migrations\Realign\AbstractSourceRealignTarget;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Realign\Support\QuotePositionedPeople;
use App\Migrations\Realign\Support\WorkOrderCostRealigner;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderStage;
use Illuminate\Database\Eloquent\Model;

/** Commesse with people, phases, payment data, flexible fields and costs (spec 0375 D-11, D-13). */
final class WorkOrdersTarget extends AbstractSourceRealignTarget
{
    private const array COLUMNS = ['title', 'start_date', 'callback_date', 'description', 'internal_notes', 'is_force_closed', 'force_close_reason'];

    public function __construct(MigrationRegistry $registry, private readonly WorkOrderCostRealigner $costs)
    {
        parent::__construct($registry);
    }

    public function source(): string
    {
        return 'work-orders';
    }

    protected function modelClass(): string
    {
        return WorkOrder::class;
    }

    public function touchSubjects(Model $record): array
    {
        return [
            [WorkOrder::class, $record->getKey()],
            [WorkOrderStage::class, WorkOrderStage::query()->where('work_order_id', $record->getKey())->select('id')],
            [WorkOrderLinePayment::class, WorkOrderLinePayment::query()->where('work_order_id', $record->getKey())->select('id')],
        ];
    }

    public function snapshot(Model $record): array
    {
        /** @var WorkOrder $record */
        return [
            ...$this->columns($record, self::COLUMNS),
            'supervisors' => $this->peopleNames($record->supervisors()),
            'participants' => QuotePositionedPeople::describe($record->participants()),
            'stages' => $this->json($this->stages($record)),
            'payments' => $this->json($this->payments($record)),
            'attribute_values' => $this->json($record->attribute_values),
            'costs' => $this->json($this->costRows($record)),
        ];
    }

    public function realign(Model $record, array $legacy, LegacyRealignNotes $notes): void
    {
        /** @var WorkOrder $record */
        parent::realign($record, $legacy, $notes);
        $this->costs->handle($record, array_values((array) ($legacy['costs'] ?? [])), $notes);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(WorkOrder $workOrder): array
    {
        $closers = User::query()->whereIn('id', $workOrder->stages()->pluck('closed_by_id')->filter())->pluck('name', 'id');

        return $workOrder->stages()->get()->map(static fn (WorkOrderStage $stage): array => [
            'name' => $stage->name,
            'sort_order' => $stage->sort_order,
            'closed_at' => $stage->getRawOriginal('closed_at'),
            'closed_by' => $closers[$stage->closed_by_id] ?? null,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function payments(WorkOrder $workOrder): array
    {
        return $workOrder->linePayments()->with('status:id,name')->orderBy('quote_line_id')->get()
            ->map(static fn (WorkOrderLinePayment $payment): array => [
                'quote_line_id' => $payment->quote_line_id,
                'status' => $payment->status?->name,
                'agreement' => $payment->payment_agreement,
                'unpaid' => $payment->has_unpaid,
            ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function costRows(WorkOrder $workOrder): array
    {
        return $workOrder->costs()->with('product:id,name')->orderBy('id')->get()
            ->map(static fn (WorkOrderCost $cost): array => [
                'product' => $cost->product?->name,
                'quote_line_id' => $cost->quote_line_id,
                'quantity' => $cost->quantity,
                'unit_price' => $cost->unit_price,
                'vat_rate_id' => $cost->vat_rate_id,
                'incurred_on' => $cost->incurred_on?->format('Y-m-d'),
                'supplier_id' => $cost->supplier_id,
                'document_reference' => $cost->document_reference,
                'description' => $cost->additional_description,
            ])->values()->all();
    }
}
