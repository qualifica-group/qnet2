<?php

namespace App\Http\Controllers\JobActivities;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\JobActivities\StoreJobActivityRequest;
use App\Http\Requests\JobActivities\UpdateJobActivityRequest;
use App\Http\Resources\JobActivityResource;
use App\Models\JobActivity;
use App\Services\JobActivityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `job-activities` resource (spec 0271). Thin controller:
 * validation (FormRequest), authorization (JobActivityPolicy), Service call, response.
 *
 * @see JobActivityService
 */
class JobActivityController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly JobActivityService $service) {}

    public function show(JobActivity $jobActivity): JsonResponse
    {
        try {
            $this->authorize('view', $jobActivity);

            return $this->ok(new JobActivityResource($this->service->withRelations($jobActivity)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobActivity' => $jobActivity->id]);
        }
    }

    public function store(StoreJobActivityRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', JobActivity::class);

            $jobActivity = $this->service->create($request->toData());

            return $this->ok(new JobActivityResource($jobActivity), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateJobActivityRequest $request, JobActivity $jobActivity): JsonResponse
    {
        try {
            $this->authorize('update', $jobActivity);

            $jobActivity = $this->service->update($jobActivity, $request->toData());

            return $this->ok(new JobActivityResource($jobActivity));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobActivity' => $jobActivity->id]);
        }
    }

    public function destroy(JobActivity $jobActivity): JsonResponse
    {
        try {
            $this->authorize('delete', $jobActivity);

            $this->service->delete($jobActivity);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobActivity' => $jobActivity->id]);
        }
    }
}
