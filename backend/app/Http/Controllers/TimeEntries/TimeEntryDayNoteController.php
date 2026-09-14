<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\UpdateTimeEntryDayNoteRequest;
use App\Models\User;
use App\Services\TimeEntries\TimeEntryDayNoteService;
use App\Services\TimeEntries\TimeEntryOwnerResolver;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * PUT /api/time-entries/day-notes (spec 0122, AC-021).
 *
 * Thin invokable controller (one action, mirrors TaskCompleteController's
 * own shape): validation (UpdateTimeEntryDayNoteRequest), authorization,
 * Service call, response.
 *
 * `time_entry_day_notes` has NO TimeEntryPolicy ability of its own — a day
 * note is not a TimeEntry record, so there is nothing for the Policy's
 * per-record ownership rule (D-8) to run against. The gate here is the
 * plain resource permission `time-entries.update`: writing a day's note is
 * an edit-in-place upsert on a (user_id, date) key (D-4), the same shape of
 * operation `update` already names for the sibling segnatempo rows —
 * `create` stays reserved for a brand-new standalone segnatempo. The
 * cross-user rule ("user_id altrui senza manageAll -> 403") is the SAME one
 * TimeEntryController::store applies to its own optional `user_id`, so it
 * is shared via TimeEntryOwnerResolver rather than re-implemented here.
 *
 * @see TimeEntryDayNoteService
 */
class TimeEntryDayNoteController extends BaseApiController
{
    public function __construct(
        private readonly TimeEntryDayNoteService $service,
        private readonly TimeEntryOwnerResolver $ownerResolver,
    ) {}

    public function __invoke(UpdateTimeEntryDayNoteRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can('time-entries.update'), 403);

            $data = $request->toData();
            $owner = $this->ownerResolver->resolve($data->userId, $actor);

            return $this->ok($this->service->upsert($data, $owner));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
