<?php

declare(strict_types=1);

namespace App\Services\Absences\Insight;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Calendar\AbsenceCalendarPeople;
use App\Services\Absences\Calendar\PersonWorkCalendar;
use App\Services\Absences\Requests\AbsenceDurationCalculator;
use App\Tables\AbsenceRequests\AbsenceRequestsToApproveTableDefinition;
use Carbon\CarbonImmutable;

/**
 * GET /api/absences/summary (spec 0304 D-18): counters for the menu badge and
 * the dashboard, the actor's next absences and who is absent today among the
 * people the actor's calendar reveals.
 */
final class AbsenceSummaryBuilder
{
    public const int UPCOMING_LIMIT = 5;

    public const int ABSENT_TODAY_LIMIT = 50;

    public function __construct(
        private readonly AbsenceRequestsToApproveTableDefinition $toApprove,
        private readonly AbsenceDurationCalculator $duration,
        private readonly AbsenceCalendarPeople $people,
        private readonly PersonWorkCalendar $calendar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $actor): array
    {
        $today = CarbonImmutable::today();

        return [
            'to_approve_count' => $this->toApprove->visibleQuery($actor)->count(),
            'my_pending_count' => AbsenceRequest::query()->where('user_id', $actor->id)->where('status', AbsenceRequestStatus::Submitted->value)->count(),
            'daily_minutes' => $this->duration->dailyMinutes($actor->id),
            'my_upcoming' => $this->upcoming($actor, $today),
            'absent_today' => $this->absentToday($actor, $today),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function upcoming(User $actor, CarbonImmutable $today): array
    {
        return AbsenceRequest::query()
            ->with('absenceType:id,name,color')
            ->where('user_id', $actor->id)
            ->whereIn('status', [AbsenceRequestStatus::Approved->value, AbsenceRequestStatus::Submitted->value])
            ->where('ends_on', '>=', $today->format('Y-m-d'))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->limit(self::UPCOMING_LIMIT)
            ->get()
            ->map(static fn (AbsenceRequest $request): array => [
                'request_id' => $request->id,
                'absence_type' => ['id' => $request->absenceType->id, 'name' => $request->absenceType->name, 'color' => $request->absenceType->color],
                'status' => $request->status->value,
                'starts_on' => $request->starts_on->format('Y-m-d'),
                'ends_on' => $request->ends_on->format('Y-m-d'),
                'is_full_day' => $request->is_full_day,
                'day_part' => $request->day_part?->value,
                'minutes' => $request->minutes,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function absentToday(User $actor, CarbonImmutable $today): array
    {
        $visible = $this->people->visibleUserIds($actor);

        if ($visible === []) {
            return [];
        }

        $date = $today->format('Y-m-d');
        $requests = AbsenceRequest::query()
            ->with(['user:id,name', 'absenceType:id,name,color'])
            ->where('status', AbsenceRequestStatus::Approved->value)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->whereIn('user_id', User::query()->where('is_active', true)->select('id'))
            ->when($visible !== null, static fn ($query) => $query->whereIn('user_id', $visible))
            ->orderBy('id')
            ->get();

        // A working day of the person: the weekend or a closure is not "absent"
        $days = $this->calendar->load($requests->pluck('user_id')->unique()->all(), $date, $date);

        return $requests
            ->reject(fn (AbsenceRequest $request): bool => $days->isNonWorkingDay($request->user_id, $date))
            ->sortBy(fn (AbsenceRequest $request): string => $request->user->name)
            ->take(self::ABSENT_TODAY_LIMIT)
            ->map(static fn (AbsenceRequest $request): array => [
                'user' => ['id' => $request->user->id, 'name' => $request->user->name],
                'request_id' => $request->id,
                'absence_type_name' => $request->absenceType->name,
                'color' => $request->absenceType->color,
                'is_full_day' => $request->is_full_day,
                'day_part' => $request->day_part?->value,
                'ends_on' => $request->ends_on->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }
}
