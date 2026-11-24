<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\CourseFieldWriter;

/**
 * Gathers the inputs of a work order's prospetto (spec 0427): the edition
 * chosen in the class select (else the link's), the class read from
 * Formalab, the quote figures, the advance/balance collected (work order
 * attributes, legacy `orderformaziones`) and the learners' partner data.
 */
final class LearnerStatementLoader
{
    public function __construct(
        private readonly CourseFieldWriter $courseWriter,
        private readonly ClassRoster $roster,
    ) {}

    /**
     * @throws ValidationException not eligible, or no class chosen
     */
    public function load(WorkOrder $workOrder, ?string $scenario = null, bool $refresh = false): LearnerStatement
    {
        // Step 1: only a work order of a Formalab category with a class has a prospetto
        $editionId = $this->editionIdOf($workOrder);

        // Step 2: the class with its learners, from Formalab (cached)
        $class = $this->roster->get($editionId, $refresh);
        $rates = LearnerFigures::fromConfig($this->totalHours($class));

        // Step 3: quote figures and the learners with their partner data
        $quote = QuoteFigures::of($workOrder);
        $payloads = is_array($class['discenti'] ?? null) ? $class['discenti'] : [];
        $book = CommissionBook::for(array_map(static fn (array $p): string => (string) ($p['fiscalcode'] ?? ''), $payloads));
        $learners = array_map(fn (array $payload): StatementLearner => $this->learner($payload, $rates, $book), $payloads);

        // Step 4: the calculator with the collected advance and balance
        $calculator = new StatementCalculator(
            $learners,
            $quote->learnerCosts(),
            $quote->totalCosts(),
            $quote->trainingRevenue(),
            $this->attributeAmount($workOrder, 'advance_attribute'),
            $this->attributeAmount($workOrder, 'balance_attribute'),
            $scenario,
        );

        return new LearnerStatement($workOrder, $editionId, $class, $quote, $book, $rates, $calculator);
    }

    private function editionIdOf(WorkOrder $workOrder): int
    {
        if (! $this->courseWriter->isEligible($workOrder)) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_eligible')]);
        }

        $editionId = $this->courseWriter->editionIdOf($workOrder)
            ?? FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->value('edition_id');

        if ($editionId === null) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.course_name_missing')]);
        }

        return (int) $editionId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function learner(array $payload, LearnerFigures $rates, CommissionBook $book): StatementLearner
    {
        $taxCode = CommissionBook::normalize((string) ($payload['fiscalcode'] ?? ''));
        $figures = $rates->of($payload);
        $passed = $payload['corso_superato'] ?? null;

        return new StatementLearner(
            payload: $payload,
            taxCode: $taxCode,
            passed: is_bool($passed) ? $passed : null,
            costHours: $figures['cost_hours'],
            cost: $figures['cost'],
            revenueHours: $figures['revenue_hours'],
            revenue: $figures['revenue'],
            margin: $figures['margin'],
            partner: $book->partnerOf($taxCode),
            percentage: $book->percentageOf($taxCode),
            amount: $book->amountOf($taxCode),
        );
    }

    /**
     * @param  array<string, mixed>|null  $class
     */
    private function totalHours(?array $class): ?int
    {
        $hours = data_get($class, 'corso.total_hours');

        return is_numeric($hours) ? (int) $hours : null;
    }

    private function attributeAmount(WorkOrder $workOrder, string $configKey): float
    {
        $value = $workOrder->attribute_values[(string) config('formalab.learner_statement.'.$configKey)] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }
}
