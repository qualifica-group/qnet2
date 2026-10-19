<?php

namespace Database\Seeders\ManagementControl;

use App\Enums\QuoteLineType;
use App\Models\QuoteLine;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderCostWriter;
use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * What happens on a commessa once it starts, line by line: the team works its hours
 * up to the delivery (McTimesheet), the line is invoiced right after it (McReceivables),
 * external teachers, rooms and subcontracting are ordered and billed by suppliers
 * (McPayables), material and travel are booked as commessa costs.
 */
final class McDelivery
{
    public const float TEACHER_RATE = 60.0;

    private const int MAX_TEAM_EXTRA = 2;

    private const int ORDER_DAYS_BEFORE = 20;

    private const array INVOICE_DELAY_DAYS = [0, 4];

    private const array SUPPLIER_BILL_DELAY_DAYS = [3, 15];

    private const array ACTUAL_FACTOR = [0.9, 1.15];

    public function __construct(
        private readonly McReceivables $receivables,
        private readonly McPayables $payables,
        private readonly McTimesheet $timesheet,
        private readonly WorkOrderCostWriter $costs,
    ) {}

    /**
     * @param  array<string, mixed>  $deal
     */
    public function schedule(McTimeline $timeline, McContext $context, Generator $faker, array $deal, WorkOrder $workOrder): void
    {
        $product = $context->products[$deal['product']];
        $quoteLines = $workOrder->quoteLines()->where('line_type', QuoteLineType::Revenue->value)->orderBy('sort_order')->orderBy('quote_lines.id')->get()->values();
        $team = $this->team($faker, $context, $workOrder, $deal['starts_on']);
        $phaseStart = $deal['starts_on'];

        foreach ($deal['lines'] as $index => $line) {
            $quoteLine = $quoteLines[$index] ?? null;
            if ($quoteLine === null) {
                continue;
            }

            // Step 1: the hours of the team up to the delivery
            $this->timesheet->plan($workOrder->id, $deal['customer_id'], $team, $phaseStart, $line['delivered_on'], McQuoteLines::hours($product, $line) * 60, $line['description']);

            // Step 2: the invoice of the delivered line
            $invoiceOn = $this->after($faker, $line['delivered_on'], self::INVOICE_DELAY_DAYS);
            $timeline->at($invoiceOn, fn (string $date) => $this->receivables->issue($timeline, $context, $faker, $workOrder, $quoteLine, $date));

            // Step 3: the external costs of the line
            $this->externalCosts($timeline, $context, $faker, $deal, $workOrder, $quoteLine, $line, $phaseStart);
            $phaseStart = $line['delivered_on'];
        }
    }

    /**
     * The supervisor plus up to two delivery people already hired on the start day.
     *
     * @return list<int>
     */
    private function team(Generator $faker, McContext $context, WorkOrder $workOrder, string $startsOn): array
    {
        $supervisorId = (int) $workOrder->supervisors()->value('users.id');
        $others = $context->supervisors
            ->filter(fn (User $user): bool => $user->id !== $supervisorId && $user->employment->hired_at->toDateString() <= $startsOn)
            ->pluck('id')->all();

        return [$supervisorId, ...$faker->randomElements($others, min(count($others), $faker->numberBetween(0, self::MAX_TEAM_EXTRA)))];
    }

    /**
     * @param  array<string, mixed>  $deal
     * @param  array<string, mixed>  $line
     */
    private function externalCosts(McTimeline $timeline, McContext $context, Generator $faker, array $deal, WorkOrder $workOrder, QuoteLine $quoteLine, array $line, string $phaseStart): void
    {
        $product = $context->products[$deal['product']];
        $orderOn = max($phaseStart, CarbonImmutable::parse($line['delivered_on'])->subDays(self::ORDER_DAYS_BEFORE)->toDateString());
        $billOn = $this->after($faker, $line['delivered_on'], self::SUPPLIER_BILL_DELAY_DAYS);
        $order = fn (array $suppliers, string $costType, string $description, int $quantity, float $price) => $this->payables->workOrderPurchase(
            $timeline, $context, $faker, $deal['company'], $workOrder->id, $context->suppliers[$faker->randomElement($suppliers)], $context->costTypes[$costType],
            sprintf('%s - %s', $description, $line['description']), $quantity, round($price * $faker->randomFloat(2, ...self::ACTUAL_FACTOR), 2), $orderOn, $billOn,
        );

        if (isset($line['participants'])) {
            if ($line['teacher_hours'] > 0) {
                $order(McCatalogue::TEACHER_SUPPLIERS, 'Docenze e subappalti', 'Docenza esterna', $line['teacher_hours'], self::TEACHER_RATE);
            }
            if ($line['room_days'] > 0) {
                $order(McCatalogue::ROOM_SUPPLIERS, 'Fitti', 'Noleggio aula attrezzata', $line['room_days'], (float) $context->products[McCatalogue::COST_ROOM]->cost);
            }
            $this->bookCosts($timeline, $context, $faker, $workOrder, $quoteLine, $line['delivered_on'], [[McCatalogue::COST_MATERIAL, $line['participants'], 'Altro']]);

            return;
        }

        $subcontract = McQuoteLines::subcontractHours($product, $line, self::TEACHER_RATE);
        if ($subcontract > 0) {
            $order(McCatalogue::IT_SUPPLIERS, 'Docenze e subappalti', 'Consulenza specialistica in subappalto', $subcontract, self::TEACHER_RATE);
        }

        if ($line['travel']) {
            $this->bookCosts($timeline, $context, $faker, $workOrder, $quoteLine, $line['delivered_on'], [
                [McCatalogue::COST_TRAIN, 2, 'Trasferte'], [McCatalogue::COST_HOTEL, $faker->numberBetween(1, 2), 'Trasferte'], [McCatalogue::COST_MEAL, $faker->numberBetween(2, 4), 'Trasferte'],
            ]);
        }
    }

    /**
     * Adds commessa cost rows on $date; the writer replaces the whole set, so the stored rows are resubmitted.
     *
     * @param  list<array{0: string, 1: int, 2: string}>  $items  [cost product name, quantity, cost type name]
     */
    private function bookCosts(McTimeline $timeline, McContext $context, Generator $faker, WorkOrder $workOrder, QuoteLine $quoteLine, string $date, array $items): void
    {
        $timeline->at($date, function () use ($context, $faker, $workOrder, $quoteLine, $date, $items): void {
            $rows = $workOrder->costs()->get()->map(fn ($cost): array => [
                'id' => $cost->id, 'product_id' => $cost->product_id, 'quote_line_id' => $cost->quote_line_id, 'cost_type_id' => $cost->cost_type_id,
                'quantity' => (string) $cost->quantity, 'unit_price' => (string) $cost->unit_price, 'vat_rate_id' => $cost->vat_rate_id,
                'incurred_on' => $cost->incurred_on?->toDateString(),
            ])->all();

            foreach ($items as [$name, $quantity, $costType]) {
                $product = $context->products[$name];
                $rows[] = [
                    'product_id' => $product->id, 'quote_line_id' => $quoteLine->id, 'cost_type_id' => $context->costTypes[$costType],
                    'quantity' => (string) $quantity, 'unit_price' => (string) round((float) $product->cost * $faker->randomFloat(2, ...self::ACTUAL_FACTOR), 2),
                    'vat_rate_id' => $product->vat_rate_id, 'incurred_on' => $date,
                ];
            }

            $this->costs->handle($workOrder, $rows);
        });
    }

    /**
     * @param  array{0: int, 1: int}  $delay
     */
    private function after(Generator $faker, string $date, array $delay): string
    {
        return McTimeline::nextWorkingDay(CarbonImmutable::parse($date)->addDays($faker->numberBetween(...$delay)))->toDateString();
    }
}
