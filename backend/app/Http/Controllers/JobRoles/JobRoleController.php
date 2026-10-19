<?php

namespace App\Http\Controllers\JobRoles;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\JobRoles\StoreJobRoleRequest;
use App\Http\Requests\JobRoles\UpdateJobRoleRequest;
use App\Http\Resources\JobRoleResource;
use App\Models\JobRole;
use App\Services\JobRoleService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD endpoints for the `job-roles` resource (spec 0271). Thin controller:
 * validation (FormRequest), authorization (JobRolePolicy), Service call, response.
 *
 * @see JobRoleService
 */
class JobRoleController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly JobRoleService $service) {}

    public function show(JobRole $jobRole): JsonResponse
    {
        try {
            $this->authorize('view', $jobRole);

            return $this->ok(new JobRoleResource($this->service->withRelations($jobRole)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobRole' => $jobRole->id]);
        }
    }

    public function store(StoreJobRoleRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', JobRole::class);

            $jobRole = $this->service->create($request->toData());

            return $this->ok(new JobRoleResource($jobRole), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateJobRoleRequest $request, JobRole $jobRole): JsonResponse
    {
        try {
            $this->authorize('update', $jobRole);

            $jobRole = $this->service->update($jobRole, $request->toData());

            return $this->ok(new JobRoleResource($jobRole));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobRole' => $jobRole->id]);
        }
    }

    public function destroy(JobRole $jobRole): JsonResponse
    {
        try {
            $this->authorize('delete', $jobRole);

            $this->service->delete($jobRole);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['jobRole' => $jobRole->id]);
        }
    }
}
