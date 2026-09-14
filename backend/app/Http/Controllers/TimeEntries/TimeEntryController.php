<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\ListTimeEntriesRequest;
use App\Http\Requests\TimeEntries\StoreTimeEntryRequest;
use App\Http\Requests\TimeEntries\UpdateTimeEntryRequest;
use App\Http\Resources\TimeEntryDaySummaryResource;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntries\TimeEntryListService;
use App\Services\TimeEntries\TimeEntryOwnerResolver;
use App\Services\TimeEntries\TimeEntryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD + list endpoints for the `time-entries` resource (spec 0122,
 * MT-B2/MT-B3), minus the stats/export endpoints — later microtasks
 * (MT-B4/B5).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TimeEntryPolicy — record-level ownership, D-8), Service call, response.
 * `store()` additionally resolves the TARGET owner via TimeEntryOwnerResolver
 * (AC-007): `time-entries.create` alone is not enough when `user_id` names
 * someone else. `index()` authorizes differently — rule R
 * (`TimeEntryReadAuthorizer`, inside `TimeEntryListService`) rather than a
 * per-record Policy check, since there is no single TimeEntry to authorize
 * against.
 *
 * @see TimeEntryService
 * @see TimeEntryListService
 */
class TimeEntryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TimeEntryService $service,
        private readonly TimeEntryOwnerResolver $ownerResolver,
        private readonly TimeEntryListService $listService,
    ) {}

    /**
     * GET /api/time-entries — the day dashboard (data_contract,
     * AC-010..AC-016, AC-021). Authorization is rule R, enforced inside
     * TimeEntryListService itself (403 on a `viewAny` miss or an
     * unauthorized `user_id`).
     */
    public function index(ListTimeEntriesRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            $query = $request->toQuery();
            $result = $this->listService->handle($query, $actor);

            return $this->paginatedResponse(
                TimeEntryDaySummaryResource::collection($result['items']),
                $result['total'],
                $result['offset'],
                $query->perPage,
                null,
                $result['meta'],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/time-entries — create a segnatempo. The D-5 link/title
     * override runs inside the Service; this method only decides WHO it
     * belongs to.
     */
    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TimeEntry::class);

            /** @var User $actor */
            $actor = $request->user();
            $data = $request->toData();
            $owner = $this->ownerResolver->resolve($data->userId, $actor);

            $entry = $this->service->create($data, $owner);

            return $this->created(new TimeEntryResource($entry));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/time-entries/{timeEntry} — 403 for a non-owner without
     * `manageAll` (D-8), 404 via route-model binding when it does not exist.
     */
    public function show(TimeEntry $timeEntry): JsonResponse
    {
        try {
            $this->authorize('view', $timeEntry);

            return $this->ok(new TimeEntryResource($this->service->loadDetail($timeEntry)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['time_entry' => $timeEntry->id]);
        }
    }

    /**
     * PUT /api/time-entries/{timeEntry} — full replace (UpdateTimeEntryRequest,
     * `user_id` prohibited: the owner never changes here, AC-009).
     */
    public function update(UpdateTimeEntryRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        try {
            $this->authorize('update', $timeEntry);

            $entry = $this->service->update($timeEntry, $request->toData());

            return $this->ok(new TimeEntryResource($entry));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['time_entry' => $timeEntry->id]);
        }
    }

    /**
     * DELETE /api/time-entries/{timeEntry} — 200 with the envelope
     * (data_contract), not a bare 204: unlike TaskController::destroy, D-8's
     * response shape is explicit about it.
     */
    public function destroy(TimeEntry $timeEntry): JsonResponse
    {
        try {
            $this->authorize('delete', $timeEntry);

            $this->service->delete($timeEntry);

            return $this->ok(null, __('Deleted'));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['time_entry' => $timeEntry->id]);
        }
    }
}
