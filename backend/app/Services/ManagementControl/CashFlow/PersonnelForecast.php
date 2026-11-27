<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\DataObjects\ManagementControl\CashFlowFilters;
use Carbon\CarbonImmutable;

/**
 * Estimated outflows for the personnel (spec 0432): net salaries (thirteenth
 * and fourteenth included) and F24, projected from the last month with costs
 * and nets imported. Estimates dated before today are omitted and the
 * projection runs one month past the horizon, so that "beyond" has a content.
 */
final class PersonnelForecast
{
    /** Share of the thirteenth and fourteenth already inside the monthly cost: 2 nets out of 12. */
    private const int ACCRUAL_NUMERATOR = 2;

    private const int ACCRUAL_DENOMINATOR = 12;

    private const int LOOKAHEAD_MONTHS = 1;

    public function __construct(private readonly PersonnelForecastData $data) {}

    /**
     * @return array{salaries: array<int, array<string, int>>, f24: array<int, array<string, int>>} cents by bucket by company id
     */
    public function cents(CashFlowFilters $filters, string $today): array
    {
        $empty = ['salaries' => [], 'f24' => []];
        // Step 1: off with the switch or with a site filter (the personnel has no site)
        if (! $filters->includePersonnel || $filters->companySiteIds !== [] || $filters->operationalSiteIds !== []) {
            return $empty;
        }

        // Step 2: the base period and the people it describes
        $base = $this->data->basePeriod();
        $people = $base === null ? [] : $this->data->people($base, $filters->companyIds);
        if ($base === null || $people === []) {
            return $empty;
        }

        // Step 3: the real data from the base period on, the payment day and the window
        $later = $this->data->later($base, $filters->companyIds);
        $salaryDay = $this->data->salaryDay($base);
        $first = CarbonImmutable::parse($base.'-01');
        $lastPayment = CarbonImmutable::parse($today)->startOfMonth()->addMonthsNoOverflow($filters->horizonMonths - 1 + self::LOOKAHEAD_MONTHS);

        // Step 4: competence months (salary and F24) and extra payments, per person
        $out = $empty;
        foreach ($people as $key => $person) {
            $terminated = PersonnelForecastData::earliest([$person['terminated'], $later['terminated'][$key] ?? null]);
            for ($competence = $first; $competence < $lastPayment; $competence = $competence->addMonthNoOverflow()) {
                if ($terminated !== null && $terminated < $competence->toDateString()) {
                    break;
                }
                $this->competence($out, $person, $later, $key, $competence, $base, $salaryDay, $today);
            }
            $this->extras($out, $person, $later, $key, $terminated, $first, $lastPayment, $today);
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, array<string, int>>>  $out
     * @param  array{company_id: int, cost: int, net: int, terminated: ?string}  $person
     * @param  array<string, mixed>  $later
     */
    private function competence(array &$out, array $person, array $later, string $key, CarbonImmutable $competence, string $base, int $salaryDay, string $today): void
    {
        $period = $competence->format('Y-m');
        $next = $competence->addMonthNoOverflow();

        // The real regular payslips of the month are already installments in the payables
        if ($period !== $base && ! isset($later['nets'][$key][$period])) {
            $this->add($out['salaries'], $person['company_id'], $this->on($next, $salaryDay), $person['net'], $today);
        }

        $cost = $later['costs'][$key][$period] ?? $person['cost'];
        $net = $later['nets'][$key][$period] ?? $person['net'];
        $accrual = (int) round($net * self::ACCRUAL_NUMERATOR / self::ACCRUAL_DENOMINATOR);
        $this->add($out['f24'], $person['company_id'], $this->on($next, (int) config('payroll.forecast.f24_day')), $cost - $net - $accrual, $today);
    }

    /**
     * Thirteenth and fourteenth: one base net each, unless a non-regular payslip already exists for the month.
     *
     * @param  array<string, array<int, array<string, int>>>  $out
     * @param  array{company_id: int, cost: int, net: int, terminated: ?string}  $person
     * @param  array<string, mixed>  $later
     */
    private function extras(array &$out, array $person, array $later, string $key, ?string $terminated, CarbonImmutable $first, CarbonImmutable $lastPayment, string $today): void
    {
        foreach ((array) config('payroll.forecast.extra_payments') as $extra) {
            for ($year = $first->year; $year <= $lastPayment->year; $year++) {
                $date = $this->on(CarbonImmutable::create($year, (int) $extra['month'], 1), (int) $extra['day']);
                $skip = $date > $lastPayment->endOfMonth()
                    || ($terminated !== null && $terminated < $date->toDateString())
                    || isset($later['extras'][$key.':'.$date->format('Y-m')]);
                if (! $skip) {
                    $this->add($out['salaries'], $person['company_id'], $date, $person['net'], $today);
                }
            }
        }
    }

    /**
     * The estimate lands in its payment month unless dated before today.
     *
     * @param  array<int, array<string, int>>  $side
     */
    private function add(array &$side, int $companyId, CarbonImmutable $date, int $cents, string $today): void
    {
        if ($cents <= 0 || $date->toDateString() < $today) {
            return;
        }
        $bucket = $date->format('Y-m');
        $side[$companyId][$bucket] = ($side[$companyId][$bucket] ?? 0) + $cents;
    }

    /** The day of the month, clamped to its length. */
    private function on(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->day(min($day, $month->daysInMonth));
    }
}
