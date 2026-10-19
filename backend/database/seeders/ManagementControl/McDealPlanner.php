<?php

namespace Database\Seeders\ManagementControl;

use Carbon\CarbonImmutable;
use Faker\Generator;

/**
 * The commercial plan of the dataset, pure data: the opportunities opened month by
 * month over the period (seasonal), each on a customer with no other deal open at
 * that time, its outcome and close date, and the offer lines it would sell — one
 * course edition or one project phase per line, with its delivery offset from the
 * commessa start, the budgeted costs and the hours it takes.
 */
final class McDealPlanner
{
    private const array CLOSE_AFTER_DAYS = [12, 55];

    private const array VALIDATE_AFTER_DAYS = [2, 12];

    private const array START_AFTER_DAYS = [3, 25];

    private const array EDITIONS = [2, 5];

    private const array PHASES = [3, 5];

    private const array PARTICIPANTS = [6, 18];

    private const int DAYS_BETWEEN_EDITIONS = 30;

    private const int PHASE_DAYS = 32;

    /** Hours per course edition (coordination, tutoring) and share of a phase value spent in hours. */
    private const array EDITION_HOURS = [16, 40];

    private const array PHASE_LABOR_SHARE = ['hr' => 0.42, 'it' => 0.36];

    /** Average hourly cost the phase hours are sized with. */
    private const float SIZING_HOURLY_COST = 16.0;

    /**
     * @param  list<int>  $customerIds
     * @return list<array<string, mixed>>
     */
    public function plan(Generator $faker, array $customerIds): array
    {
        $busyUntil = array_fill_keys($customerIds, '0000-00-00');
        $deals = [];

        for ($month = CarbonImmutable::parse(McCatalogue::PERIOD_START); $month->toDateString() <= McCatalogue::PERIOD_END; $month = $month->addMonth()) {
            $count = (int) round(McCatalogue::DEALS_PER_MONTH * (McCatalogue::SEASONAL_FACTOR[$month->month] ?? 1.0) * $faker->randomFloat(2, 0.85, 1.15));

            for ($i = 0; $i < $count; $i++) {
                $openedOn = McTimeline::nextWorkingDay($month->addDays($faker->numberBetween(0, $month->daysInMonth - 1)));
                $customerId = $this->freeCustomer($faker, $busyUntil, $openedOn->toDateString());
                if ($customerId === null || $openedOn->toDateString() > McCatalogue::PERIOD_END) {
                    continue;
                }

                $deal = $this->deal($faker, $customerId, $openedOn);
                $busyUntil[$customerId] = $deal['closes_on'];
                $deals[] = $deal;
            }
        }

        return $deals;
    }

    /**
     * @param  array<int, string>  $busyUntil
     */
    private function freeCustomer(Generator $faker, array $busyUntil, string $date): ?int
    {
        $free = array_keys(array_filter($busyUntil, fn (string $until): bool => $until < $date));

        return $free === [] ? null : $faker->randomElement($free);
    }

    /**
     * @return array<string, mixed>
     */
    private function deal(Generator $faker, int $customerId, CarbonImmutable $openedOn): array
    {
        $training = $faker->boolean(55);
        $closesOn = McTimeline::nextWorkingDay($openedOn->addDays($faker->numberBetween(...self::CLOSE_AFTER_DAYS)));
        $validatedOn = McTimeline::nextWorkingDay($closesOn->addDays($faker->numberBetween(...self::VALIDATE_AFTER_DAYS)));
        $startsOn = McTimeline::nextWorkingDay($validatedOn->addDays($faker->numberBetween(...self::START_AFTER_DAYS)));
        $product = $faker->randomElement(array_keys($training ? McCatalogue::TRAINING_PRODUCTS : McCatalogue::CONSULTING_PRODUCTS));

        return [
            'customer_id' => $customerId,
            'kind' => $training ? 'training' : 'consulting',
            'product' => $product,
            'opened_on' => $openedOn->toDateString(),
            'closes_on' => $closesOn->toDateString(),
            'won' => $faker->boolean(McCatalogue::WIN_PERCENT),
            'validated_on' => $validatedOn->toDateString(),
            'starts_on' => $startsOn->toDateString(),
            'probability' => $faker->randomElement([30, 40, 50, 60, 70, 80]),
            'lines' => $training ? $this->editions($faker, $product, $startsOn) : $this->phases($faker, $product, $startsOn),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function editions(Generator $faker, string $product, CarbonImmutable $startsOn): array
    {
        $lines = [];
        foreach (range(1, $faker->numberBetween(...self::EDITIONS)) as $edition) {
            $deliveredOn = McTimeline::nextWorkingDay($startsOn->addDays(($edition - 1) * self::DAYS_BETWEEN_EDITIONS + $faker->numberBetween(5, 15)));
            $participants = $faker->numberBetween(...self::PARTICIPANTS);
            $teacherHours = $faker->randomElement([8, 12, 16, 24]);
            $external = $faker->boolean(65);

            $lines[] = [
                'description' => sprintf('Edizione %d - %s, sede di %s', $edition, $deliveredOn->locale('it')->translatedFormat('F Y'), $faker->randomElement(McCatalogue::CITIES)),
                'quantity' => $participants,
                'price_factor' => $faker->randomFloat(2, 0.9, 1.1),
                'delivered_on' => $deliveredOn->toDateString(),
                // coordination and tutoring, plus the teaching itself when no external teacher is hired
                'hours' => $faker->numberBetween(...self::EDITION_HOURS) + ($external ? 0 : $teacherHours),
                'teacher_hours' => $external ? $teacherHours : 0,
                'room_days' => $faker->boolean(50) ? max(1, intdiv($teacherHours, 8)) : 0,
                'participants' => $participants,
                'travel' => false,
            ];
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function phases(Generator $faker, string $product, CarbonImmutable $startsOn): array
    {
        $area = McCatalogue::CONSULTING_PRODUCTS[$product][1];
        $names = McCatalogue::PHASES[$area];
        $lines = [];

        foreach (range(1, $faker->numberBetween(...self::PHASES)) as $phase) {
            $factor = $faker->randomFloat(2, 0.8, 1.6);
            $lines[] = [
                'description' => sprintf('Fase %d - %s', $phase, $names[($phase - 1) % count($names)]),
                'quantity' => 1,
                'price_factor' => $factor,
                'delivered_on' => McTimeline::nextWorkingDay($startsOn->addDays($phase * self::PHASE_DAYS))->toDateString(),
                // the hours are sized on the phase value at the reference price, refined by the caller
                'labor_share' => self::PHASE_LABOR_SHARE[$area] * $faker->randomFloat(2, 0.75, 1.3),
                'sizing_hourly_cost' => self::SIZING_HOURLY_COST,
                'subcontract_share' => $area === 'it' && $faker->boolean(55) ? $faker->randomFloat(2, 0.15, 0.3) : 0.0,
                'travel' => $faker->boolean(60),
            ];
        }

        return $lines;
    }
}
