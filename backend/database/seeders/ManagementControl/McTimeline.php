<?php

namespace Database\Seeders\ManagementControl;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use SplPriorityQueue;

/**
 * The dataset's clock: events are queued with their date and run in date order, each
 * with the application clock set to that day (Carbon::setTestNow), so numbering,
 * created_at and every now() of the services fall on the simulated day. An event may
 * queue later events (an issued invoice queues its collections). Nothing runs after
 * the real today: the period ends in the future, the future stays open.
 */
final class McTimeline
{
    /** Office hour the events of a day run at. */
    private const int EVENT_HOUR = 10;

    private SplPriorityQueue $queue;

    private int $sequence = PHP_INT_MAX;

    private CarbonImmutable $today;

    public function __construct()
    {
        $this->queue = new SplPriorityQueue;
        $this->queue->setExtractFlags(SplPriorityQueue::EXTR_DATA);
        $this->today = CarbonImmutable::today();
    }

    public function today(): CarbonImmutable
    {
        return $this->today;
    }

    public function isPast(string $date): bool
    {
        return $date <= $this->today->toDateString();
    }

    /** Queues $event on $date; same-day events keep their queuing order. Future dates are dropped. */
    public function at(string $date, Closure $event): void
    {
        if (! $this->isPast($date)) {
            return;
        }

        // The queue extracts the highest priority first: the earliest date wins, then the earliest queued
        $this->queue->insert([$date, $event], [-(int) str_replace('-', '', $date), $this->sequence--]);
    }

    /** Runs every queued event in date order; returns how many ran. */
    public function run(): int
    {
        $count = 0;

        try {
            while (! $this->queue->isEmpty()) {
                [$date, $event] = $this->queue->extract();
                Carbon::setTestNow(Carbon::parse($date)->setTime(self::EVENT_HOUR, 0));
                $event($date);
                $count++;
            }
        } finally {
            Carbon::setTestNow();
        }

        return $count;
    }

    /** A working day: Monday to Friday, no bank holiday, no company closure. */
    public static function isWorkingDay(CarbonImmutable $day): bool
    {
        if ($day->isWeekend() || in_array($day->format('m-d'), McCatalogue::HOLIDAYS, true)) {
            return false;
        }

        $date = $day->toDateString();
        if (in_array($date, McCatalogue::EASTER_MONDAYS, true)) {
            return false;
        }

        foreach (McCatalogue::CLOSURES as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                return false;
            }
        }

        return true;
    }

    /** The first working day on or after $date. */
    public static function nextWorkingDay(CarbonImmutable $date): CarbonImmutable
    {
        while (! self::isWorkingDay($date)) {
            $date = $date->addDay();
        }

        return $date;
    }

    /**
     * Working days in [$from, $to].
     *
     * @return list<string>
     */
    public static function workingDays(string $from, string $to): array
    {
        $days = [];
        for ($day = CarbonImmutable::parse($from); $day->toDateString() <= $to; $day = $day->addDay()) {
            if (self::isWorkingDay($day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }
}
