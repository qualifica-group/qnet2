<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

/**
 * The validated GET /api/time-entries request (spec 0122, data_contract):
 * F (as `TimeEntryFilterData`) plus the sort/pagination that only the list
 * endpoint takes — overview/pulse accept F alone.
 */
final readonly class TimeEntryListQuery
{
    public function __construct(
        public TimeEntryFilterData $filter,
        public string $sortBy,
        public string $sortDirection,
        public int $page,
        public int $perPage,
    ) {}
}
