<?php

declare(strict_types=1);

namespace App\Services\Absences\Insight;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Calendar\AbsenceCalendarPeople;
use App\Services\Absences\Calendar\AbsenceCalendarScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * "Absent colleagues" of a person over a period (spec 0304 D-16): active people
 * other than the person who share a business function or a direct manager with
 * them, with an approved or submitted request in the period, restricted to
 * whom the ACTOR sees in the calendar (a calendarOwn-only actor sees nobody).
 * Constant number of queries, capped at MAX_ENTRIES.
 */
final class AbsenceColleaguesResolver
{
    public const int MAX_ENTRIES = 50;

    public function __construct(private readonly AbsenceCalendarPeople $people) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forPerson(User $actor, int $personId, string $from, string $to): array
    {
        // Own-only or no calendar permission: nobody is a visible colleague
        if (in_array(AbsenceCalendarScope::forActor($actor), [null, AbsenceCalendarScope::Own], true)) {
            return [];
        }

        $visible = $this->people->visibleUserIds($actor);

        $requests = AbsenceRequest::query()
            ->with(['user:id,name', 'absenceType:id,name,color'])
            ->whereIn('status', [AbsenceRequestStatus::Approved->value, AbsenceRequestStatus::Submitted->value])
            ->where('starts_on', '<=', $to)
            ->where('ends_on', '>=', $from)
            ->where('user_id', '!=', $personId)
            ->whereIn('user_id', User::query()->where('is_active', true)->select('id'))
            ->where(fn ($query) => $query
                ->whereIn('user_id', $this->sharingFunction($personId))
                ->orWhereIn('user_id', $this->sharingManager($personId)))
            ->when($visible !== null, static fn ($query) => $query->whereIn('user_id', $visible))
            ->orderBy('starts_on')
            ->orderBy('id')
            ->limit(self::MAX_ENTRIES)
            ->get();

        return $requests->map(static fn (AbsenceRequest $request): array => [
            'user' => ['id' => $request->user->id, 'name' => $request->user->name],
            'request_id' => $request->id,
            'status' => $request->status->value,
            'starts_on' => $request->starts_on->format('Y-m-d'),
            'ends_on' => $request->ends_on->format('Y-m-d'),
            'absence_type_name' => $request->absenceType->name,
            'color' => $request->absenceType->color,
            'is_full_day' => $request->is_full_day,
            'day_part' => $request->day_part?->value,
        ])->all();
    }

    private function sharingFunction(int $personId): QueryBuilder
    {
        return DB::table('business_function_user')
            ->select('user_id')
            ->whereIn('business_function_id', DB::table('business_function_user')->select('business_function_id')->where('user_id', $personId));
    }

    private function sharingManager(int $personId): QueryBuilder
    {
        $managers = DB::table('employment_profile_manager')
            ->join('employment_profiles', 'employment_profiles.id', '=', 'employment_profile_manager.employment_profile_id')
            ->where('employment_profiles.user_id', $personId)
            ->select('employment_profile_manager.user_id');

        return DB::table('employment_profiles')
            ->join('employment_profile_manager', 'employment_profile_manager.employment_profile_id', '=', 'employment_profiles.id')
            ->whereIn('employment_profile_manager.user_id', $managers)
            ->select('employment_profiles.user_id');
    }
}
