<?php

namespace App\Http\Controllers\AbsenceCalendar;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceCalendar\AbsenceCalendarRequest;
use App\Models\User;
use App\Services\Absences\Calendar\AbsenceCalendarBuilder;
use App\Services\Absences\Calendar\AbsenceCalendarScope;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/absences/calendar (spec 0279): who is absent, day by day, among
 * the people the actor's calendar permission reveals.
 */
class AbsenceCalendarController extends BaseApiController
{
    public function __construct(private readonly AbsenceCalendarBuilder $calendar) {}

    public function index(AbsenceCalendarRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->calendar->build(
                $actor,
                AbsenceCalendarScope::forActor($actor),
                $request->string('from')->toString(),
                $request->string('to')->toString(),
                $request->filled('business_function_id') ? $request->integer('business_function_id') : null,
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
