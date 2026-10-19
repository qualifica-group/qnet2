<?php

namespace Database\Seeders\ManagementControl;

use Carbon\CarbonImmutable;
use Faker\Generator;
use Illuminate\Support\Facades\DB;

/**
 * The segnatempo of the dataset, the source of the labor cost (specs 0272/0277/0282).
 * The commesse book their hours first (plan): spread over the team's working days of
 * the line, always on the least loaded day. Then every person fills each working day
 * up to the standard with generic hours with no commessa (flush), which the
 * management control charges to the person's business functions. Absences and the
 * company closures leave days empty. Rows are bulk inserted: they carry no side
 * effect a service would add (no task, no notes).
 */
final class McTimesheet
{
    private const int BLOCK_MINUTES = 30;

    private const array WORK_BLOCK = [2, 8];

    private const int MAX_DAY_MINUTES = 480;

    private const int ABSENCE_PERCENT = 7;

    private const int CHUNK = 1000;

    private const string WORK_ORDER_TASK_TYPE = 'Attività';

    private const array GENERIC_TASK_TYPES = ['Riunione', 'Altro', 'Attività'];

    /** @var array<int, array<string, int>> booked minutes by user id and date */
    private array $booked = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** The real today, taken before the timeline moves the clock. */
    private readonly string $today;

    public function __construct(private readonly Generator $faker)
    {
        $this->today = CarbonImmutable::today()->toDateString();
    }

    /**
     * Books $minutes of commessa work for $userIds over the working days in [$from, $to].
     *
     * @param  list<int>  $userIds  the first one (the supervisor) takes the largest share
     */
    public function plan(int $workOrderId, int $registryId, array $userIds, string $from, string $to, int $minutes, string $title): void
    {
        $days = array_values(array_filter(McTimeline::workingDays($from, $to), fn (string $day): bool => $day <= $this->today));
        if ($days === [] || $minutes <= 0) {
            return;
        }

        $remaining = intdiv($minutes, self::BLOCK_MINUTES) * self::BLOCK_MINUTES;
        $position = 0;

        while ($remaining > 0) {
            $userId = $position % 2 === 0 || count($userIds) === 1 ? $userIds[0] : $userIds[1 + $position % (count($userIds) - 1)];
            $block = min($remaining, $this->faker->numberBetween(...self::WORK_BLOCK) * self::BLOCK_MINUTES);
            $day = $this->leastLoaded($userId, $days);

            if (($this->booked[$userId][$day] ?? 0) + $block > self::MAX_DAY_MINUTES) {
                // the team is full on this line: the excess hours are not worked
                break;
            }

            $this->book($userId, $day, $block, $title, $workOrderId, $registryId, self::WORK_ORDER_TASK_TYPE);
            $remaining -= $block;
            $position++;
        }
    }

    /**
     * Fills every working day of the roster with generic hours and writes all the rows.
     *
     * @param  list<array{user_id: int, hired_at: string, delivery: bool, daily_minutes: int}>  $roster
     * @return int the rows written
     */
    public function flush(array $roster): int
    {
        $taskTypes = DB::table('task_types')->pluck('id', 'name');
        $until = min($this->today, McCatalogue::PERIOD_END);

        foreach ($roster as $person) {
            foreach (McTimeline::workingDays(max($person['hired_at'], McCatalogue::PERIOD_START), $until) as $day) {
                $free = $person['daily_minutes'] - ($this->booked[$person['user_id']][$day] ?? 0);
                if ($free <= 0 || ($this->faker->boolean(self::ABSENCE_PERCENT) && ! isset($this->booked[$person['user_id']][$day]))) {
                    continue;
                }

                $this->fillGeneric($person['user_id'], $day, $free);
            }
        }

        foreach ($this->rows as &$row) {
            $row['task_type_id'] = $taskTypes[$row['task_type_id']] ?? $taskTypes->first();
        }
        unset($row);

        foreach (array_chunk($this->rows, self::CHUNK) as $chunk) {
            DB::table('time_entries')->insert($chunk);
        }

        return count($this->rows);
    }

    private function fillGeneric(int $userId, string $day, int $free): void
    {
        $first = $free > 4 * 60 ? intdiv($free, 2 * self::BLOCK_MINUTES) * self::BLOCK_MINUTES : $free;

        foreach (array_filter([$first, $free - $first]) as $minutes) {
            $this->book($userId, $day, $minutes, $this->faker->randomElement(McCatalogue::GENERIC_ACTIVITIES), null, null, $this->faker->randomElement(self::GENERIC_TASK_TYPES));
        }
    }

    /**
     * @param  list<string>  $days
     */
    private function leastLoaded(int $userId, array $days): string
    {
        $best = $days[0];
        foreach ($days as $day) {
            if (($this->booked[$userId][$day] ?? 0) < ($this->booked[$userId][$best] ?? 0)) {
                $best = $day;
            }
        }

        return $best;
    }

    private function book(int $userId, string $day, int $minutes, string $title, ?int $workOrderId, ?int $registryId, string $taskType): void
    {
        $this->booked[$userId][$day] = ($this->booked[$userId][$day] ?? 0) + $minutes;
        $now = CarbonImmutable::parse($day)->setTime(18, 0)->toDateTimeString();

        $this->rows[] = [
            'user_id' => $userId, 'date' => $day, 'title' => mb_substr($title, 0, 191), 'task_type_id' => $taskType,
            'start_time' => null, 'end_time' => null, 'minutes' => $minutes, 'notes' => null,
            'registry_id' => $registryId, 'opportunity_id' => null, 'work_order_id' => $workOrderId, 'task_id' => null,
            'created_at' => $now, 'updated_at' => $now,
        ];
    }
}
