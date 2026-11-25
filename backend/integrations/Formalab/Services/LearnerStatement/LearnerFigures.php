<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services\LearnerStatement;

/**
 * Hours and amounts of one learner, as the legacy prospetto computes them
 * (`calcolaImporti`, spec 0427 D-1): the cost is paid on the whole hours
 * attended, the revenue on the hours rounded DOWN to the quarter, both capped
 * at the course total hours when the course declares them.
 */
final class LearnerFigures
{
    private const int MINUTES_PER_QUARTER = 15;

    private const float HOURS_PER_QUARTER = 0.25;

    private const int MINUTES_PER_HOUR = 60;

    public function __construct(
        private readonly float $costPerHour,
        private readonly float $revenuePerHour,
        private readonly ?int $totalHours,
    ) {}

    public static function fromConfig(?int $totalHours): self
    {
        return new self(
            (float) config('formalab.learner_statement.cost_per_hour'),
            (float) config('formalab.learner_statement.revenue_per_hour'),
            $totalHours,
        );
    }

    public function costPerHour(): float
    {
        return $this->costPerHour;
    }

    public function revenuePerHour(): float
    {
        return $this->revenuePerHour;
    }

    /**
     * @param  array<string, mixed>  $learner  one `classe.discenti` item of the Formalab payload
     * @return array{cost_hours: int|float, cost: float, revenue_hours: int|float, revenue: float, margin: float}
     */
    public function of(array $learner): array
    {
        $hours = (int) ($learner['ore_presenza'] ?? 0);
        $minutes = (int) ($learner['minuti_presenza'] ?? 0);

        $costHours = $this->capped($hours);
        $cost = $costHours * $this->costPerHour;
        $revenueHours = $this->capped($this->quarterHours($hours, $minutes));
        $revenue = $revenueHours * $this->revenuePerHour;

        return [
            'cost_hours' => $costHours,
            'cost' => round($cost, 2),
            'revenue_hours' => $revenueHours,
            'revenue' => round($revenue, 2),
            'margin' => round($revenue - $cost, 2),
        ];
    }

    /**
     * Minutes rounded down to the quarter hour: 1-14 -> 0, 15-29 -> 0.25,
     * 30-44 -> 0.50, 45-59 -> 0.75; anything else adds nothing.
     */
    public function quarterHours(int $hours, int $minutes): float
    {
        $quarters = $minutes >= 1 && $minutes < self::MINUTES_PER_HOUR
            ? intdiv($minutes, self::MINUTES_PER_QUARTER) * self::HOURS_PER_QUARTER
            : 0.0;

        return $hours + $quarters;
    }

    /** The course total hours are the ceiling; no total (null or 0) means no ceiling. */
    private function capped(int|float $hours): int|float
    {
        if (empty($this->totalHours) || $hours <= $this->totalHours) {
            return $hours;
        }

        return $this->totalHours;
    }
}
