<?php

namespace App\Http\Controllers\AbsenceBalances;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceBalances\AbsenceBalanceIndexRequest;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\Absences\Balances\AbsenceBalanceBuilder;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/absence-balances (spec 0278 D-1, D-10). Own balances by default;
 * another person's only for a manager of theirs or the HR office (manageAll).
 */
class AbsenceBalanceController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceBalanceBuilder $balances,
        private readonly TimeEntrySubordinateResolver $subordinates,
    ) {}

    public function index(AbsenceBalanceIndexRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            // Step 1: resolve the person and check they are visible
            $person = $request->filled('user_id') ? User::query()->findOrFail($request->integer('user_id')) : $actor;
            abort_unless($this->canView($actor, $person), 403, __('absence_balances.person_not_visible'));

            // Step 2: build the rows
            $year = $request->integer('year', (int) today()->year);

            return $this->ok([
                'user' => ['id' => $person->id, 'name' => $person->name],
                'year' => $year,
                'daily_minutes' => $this->balances->dailyMinutes(EmploymentProfile::query()->where('user_id', $person->id)->first()),
                'balances' => $this->balances->forUser($person, $year),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function canView(User $actor, User $person): bool
    {
        return $actor->is($person)
            || $actor->can('absence-requests.manageAll')
            || $this->subordinates->isDescendantOf($person->id, $actor->id);
    }
}
