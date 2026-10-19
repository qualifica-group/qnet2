<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * One approved absence as it lands on a single day of the segnatempo
 * (spec 0278, D-3): `minutes` is the base target for a full-day absence, the
 * requested minutes for an hourly one.
 */
final readonly class DayAbsenceData
{
    public function __construct(
        public int $id,
        public string $absenceTypeName,
        public bool $isFullDay,
        public int $minutes,
    ) {}
}
