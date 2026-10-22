<?php

declare(strict_types=1);

namespace App\Services\Absences\Insight;

use App\DataObjects\Absences\AbsenceRequestData;
use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\User;
use App\Services\Absences\Balances\AbsenceBalanceBuilder;
use App\Services\Absences\Calendar\PersonNonWorkingDays;
use App\Services\Absences\Calendar\PersonWorkCalendar;
use App\Services\Absences\Requests\AbsenceDurationCalculator;
use App\Services\Absences\Requests\AbsenceNoticeRule;
use Carbon\CarbonImmutable;

/**
 * The AbsenceRequestInsight of spec 0304: what a request (draft payload or
 * stored request) amounts to for the person and who of the team is absent.
 * Same calculators as the submission, so the preview never disagrees with the
 * number that is frozen later. Writes nothing.
 */
final class AbsenceInsightBuilder
{
    public function __construct(
        private readonly AbsenceDurationCalculator $duration,
        private readonly PersonWorkCalendar $calendar,
        private readonly AbsenceBalanceBuilder $balances,
        private readonly AbsenceNoticeRule $notice,
        private readonly AbsenceColleaguesResolver $colleagues,
    ) {}

    /**
     * Preview of a write payload; `$ignoreRequestId` is the draft being edited.
     *
     * @return array<string, mixed>
     */
    public function forPayload(User $actor, User $person, AbsenceRequestData $data, ?int $ignoreRequestId): array
    {
        // Step 1: duration and notice as the submission would fix them
        $type = AbsenceType::query()->findOrFail($data->absenceTypeId);
        $minutes = $this->duration->minutes($person->id, $data->isFullDay, $data->startsOn, $data->endsOn, $data->requestedMinutes, $data->dayPart);
        $isShort = $this->notice->isShort($type, $data->startsOn, $actor->id !== $person->id);

        // Step 2: everything else around the period
        return $this->assemble($actor, $person, $type, $data->isFullDay, $data->startsOn, $data->endsOn, $minutes, $isShort, false, $ignoreRequestId);
    }

    /**
     * Insight of a stored request: once filed, minutes and notice are the frozen ones.
     *
     * @return array<string, mixed>
     */
    public function forRequest(User $actor, AbsenceRequest $request): array
    {
        $person = User::query()->findOrFail($request->user_id);
        $type = AbsenceType::query()->findOrFail($request->absence_type_id);
        $startsOn = $request->starts_on->format('Y-m-d');
        $endsOn = $request->ends_on->format('Y-m-d');

        if ($request->status === AbsenceRequestStatus::Draft) {
            $minutes = $this->duration->minutes($person->id, $request->is_full_day, $startsOn, $endsOn, $request->requested_minutes, $request->day_part);
            $isShort = $this->notice->isShort($type, $startsOn, (int) $request->created_by !== (int) $request->user_id);
        } else {
            $minutes = (int) $request->minutes;
            $isShort = $request->is_short_notice;
        }

        return $this->assemble($actor, $person, $type, $request->is_full_day, $startsOn, $endsOn, $minutes, $isShort, $request->status === AbsenceRequestStatus::Approved, $request->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(
        User $actor,
        User $person,
        AbsenceType $type,
        bool $isFullDay,
        string $startsOn,
        string $endsOn,
        int $minutes,
        bool $isShort,
        bool $alreadyDeducted,
        ?int $requestId,
    ): array {
        $days = $this->calendar->load([$person->id], $startsOn, $endsOn);
        $nonWorking = $this->nonWorkingDays($days, $person->id, $startsOn, $endsOn);

        return [
            'person' => ['id' => $person->id, 'name' => $person->name],
            'daily_minutes' => $this->duration->dailyMinutes($person->id),
            'working_days' => $isFullDay ? $this->daySpan($startsOn, $endsOn) - count($nonWorking) : 1,
            'minutes' => $minutes,
            'non_working_days' => $nonWorking,
            'balance' => $type->deducts_balance ? $this->balance($person, $type, $startsOn, $minutes, $alreadyDeducted) : null,
            'notice' => ['min_notice_days' => $type->min_notice_days, 'is_short_notice' => $isShort],
            'overlaps' => $this->overlaps($person->id, $startsOn, $endsOn, $requestId),
            'colleagues_absent' => $this->colleagues->forPerson($actor, $person->id, $startsOn, $endsOn),
        ];
    }

    /**
     * The balance of the year of the start; an approved request is already part of the used minutes,
     * any other one still has to be taken off the residual.
     *
     * @return array{year: int, residual_minutes: int, residual_after_minutes: int}
     */
    private function balance(User $person, AbsenceType $type, string $startsOn, int $minutes, bool $alreadyDeducted): array
    {
        $year = (int) substr($startsOn, 0, 4);
        $residual = (int) $this->balances->row($person, $type, $year)['residual_minutes'];

        return [
            'year' => $year,
            'residual_minutes' => $residual,
            'residual_after_minutes' => $alreadyDeducted ? $residual : $residual - $minutes,
        ];
    }

    /**
     * @return list<array{date: string, reason: string, name: string|null}>
     */
    private function nonWorkingDays(PersonNonWorkingDays $days, int $userId, string $startsOn, string $endsOn): array
    {
        $found = [];

        for ($day = CarbonImmutable::parse($startsOn); $day->format('Y-m-d') <= $endsOn; $day = $day->addDay()) {
            $date = $day->format('Y-m-d');

            if (($reason = $days->reason($userId, $date)) !== null) {
                $found[] = ['date' => $date, ...$reason];
            }
        }

        return $found;
    }

    private function daySpan(string $startsOn, string $endsOn): int
    {
        return (int) CarbonImmutable::parse($startsOn)->diffInDays(CarbonImmutable::parse($endsOn)) + 1;
    }

    /**
     * Own submitted or approved requests touching the period (shown, never a 422 here).
     *
     * @return list<array<string, mixed>>
     */
    private function overlaps(int $userId, string $startsOn, string $endsOn, ?int $ignoreRequestId): array
    {
        return AbsenceRequest::query()
            ->with('absenceType:id,name')
            ->where('user_id', $userId)
            ->whereIn('status', [AbsenceRequestStatus::Submitted->value, AbsenceRequestStatus::Approved->value])
            ->where('starts_on', '<=', $endsOn)
            ->where('ends_on', '>=', $startsOn)
            ->when($ignoreRequestId !== null, static fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->orderBy('starts_on')
            ->get()
            ->map(static fn (AbsenceRequest $request): array => [
                'request_id' => $request->id,
                'status' => $request->status->value,
                'starts_on' => $request->starts_on->format('Y-m-d'),
                'ends_on' => $request->ends_on->format('Y-m-d'),
                'absence_type_name' => $request->absenceType->name,
            ])
            ->all();
    }
}
