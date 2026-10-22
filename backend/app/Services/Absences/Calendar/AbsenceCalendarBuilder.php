<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use App\Services\TimeEntries\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The absence calendar of a period (spec 0279): the days with their
 * non-working flags and, for every visible person, the approved and pending
 * absences split by working day. Reads the requests of spec 0278, copies
 * nothing; the query count does not grow with the number of people.
 */
final class AbsenceCalendarBuilder
{
    private const array SHOWN_STATUSES = [AbsenceRequestStatus::Approved, AbsenceRequestStatus::Submitted];

    public function __construct(
        private readonly WorkCalendar $calendar,
        private readonly PersonWorkCalendar $personCalendar,
        private readonly TimeEntrySubordinateResolver $subordinates,
        private readonly AbsenceRequestVisibility $visibility,
    ) {}

    /**
     * @return array{scope: string, days: list<array<string, mixed>>, people: list<array<string, mixed>>}
     */
    public function build(User $actor, AbsenceCalendarScope $scope, string $from, string $to, ?int $businessFunctionId): array
    {
        // Step 1: the people the scope reveals, narrowed by function
        $people = $this->people($actor, $scope, $businessFunctionId);

        // Step 2: their approved and pending requests touching the period
        $requests = $this->requests($people->modelKeys(), $from, $to)->groupBy('user_id');

        // Step 3: the closures of each person, then one entry per working day
        // of the person for each request
        $nonWorking = $this->personCalendar->load($people->modelKeys(), $from, $to);
        $canOpen = $this->visibility->viewChecker($actor);
        $days = $this->days($from, $to);
        $nationalWorkingDates = array_column(array_filter($days, static fn (array $day): bool => ! $day['is_non_working_day']), 'date');

        return [
            'scope' => $scope->value,
            'days' => $days,
            'people' => $people->map(fn (User $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'closed_days' => $nonWorking->closedDays($person->id),
                'absences' => $this->absenceDays(
                    $requests->get($person->id, collect()),
                    array_values(array_filter($nationalWorkingDates, static fn (string $date): bool => ! $nonWorking->isNonWorkingDay($person->id, $date))),
                    $canOpen,
                ),
            ])->values()->all(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    private function people(User $actor, AbsenceCalendarScope $scope, ?int $businessFunctionId): Collection
    {
        $query = User::query()->select(['id', 'name'])->orderBy('name')->orderBy('id');

        match ($scope) {
            AbsenceCalendarScope::All => $query->where('is_active', true),
            AbsenceCalendarScope::Team => $query->whereKey([$actor->id, ...$this->subordinates->descendantIds($actor->id)]),
            AbsenceCalendarScope::Own => $query->whereKey($actor->id),
        };

        if ($businessFunctionId !== null) {
            $query->whereHas('businessFunctions', static fn (Builder $functions) => $functions->whereKey($businessFunctionId));
        }

        return $query->get();
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int, AbsenceRequest>
     */
    private function requests(array $userIds, string $from, string $to): Collection
    {
        return AbsenceRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('status', self::SHOWN_STATUSES)
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->with(['absenceType:id,name,color', 'approvers:id'])
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * @return list<array{date: string, is_non_working_day: bool, is_holiday: bool}>
     */
    private function days(string $from, string $to): array
    {
        $days = [];

        for ($day = CarbonImmutable::parse($from); $day->format('Y-m-d') <= $to; $day = $day->addDay()) {
            $date = $day->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'is_non_working_day' => $this->calendar->isNonWorkingDay($date),
                'is_holiday' => $this->calendar->isHoliday($date),
            ];
        }

        return $days;
    }

    /**
     * Weekends, holidays and the person's closures inside a request are skipped, as in its duration (spec 0278 D-6, 0304 D-14).
     *
     * @param  Collection<int, AbsenceRequest>  $requests
     * @param  list<string>  $workingDates  the working days of the person
     * @param  callable(AbsenceRequest): bool  $canOpen
     * @return list<array<string, mixed>>
     */
    private function absenceDays(Collection $requests, array $workingDates, callable $canOpen): array
    {
        $entries = [];

        foreach ($requests as $request) {
            $startsOn = $request->starts_on->format('Y-m-d');
            $endsOn = $request->ends_on->format('Y-m-d');
            $openable = $canOpen($request);

            foreach ($workingDates as $date) {
                if ($date < $startsOn || $date > $endsOn) {
                    continue;
                }

                $entries[] = [
                    'request_id' => $request->id,
                    'date' => $date,
                    'status' => $request->status->value,
                    'absence_type_name' => $request->absenceType->name,
                    'color' => $request->absenceType->color,
                    'day_part' => $request->day_part?->value,
                    'is_full_day' => $request->is_full_day,
                    'minutes' => $request->is_full_day ? null : $request->minutes,
                    'can_open' => $openable,
                ];
            }
        }

        return $entries;
    }
}
