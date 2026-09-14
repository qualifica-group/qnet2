<?php

namespace App\Http\Resources;

use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `DaySummary` shape (spec 0122, data_contract GET /api/time-entries),
 * wrapping `TimeEntryDaySummaryData`. `entries` reuses `TimeEntryResource`
 * verbatim — the SAME `TimeEntry` shape as store/show/update — so the two
 * never drift.
 *
 * @mixin TimeEntryDaySummaryData
 */
class TimeEntryDaySummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TimeEntryDaySummaryData $day */
        $day = $this->resource;

        return [
            'date' => $day->date,
            'weekday' => $day->weekday,
            'is_holiday' => $day->isHoliday,
            'is_non_working_day' => $day->isNonWorkingDay,
            'is_active' => $day->isActive,
            'target_minutes' => $day->targetMinutes,
            'total_minutes' => $day->totalMinutes,
            'utilization_percentage' => $day->utilizationPercentage,
            'status' => $day->status->value,
            'day_note' => $day->dayNote,
            'entries' => TimeEntryResource::collection($day->entries),
        ];
    }
}
