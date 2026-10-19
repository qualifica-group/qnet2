<?php

namespace App\Http\Controllers\StaffCourses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\StaffCourses\StoreStaffCourseRequest;
use App\Http\Requests\StaffCourses\UpdateStaffCourseRequest;
use App\Http\Resources\StaffCourseResource;
use App\Models\StaffCourse;
use App\Models\User;
use App\Services\StaffCourseService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `staff-courses` resource (spec 0284 D-2). Thin
 * controller: validation (FormRequest), authorization (StaffCoursePolicy),
 * Service call, response with the `permissions` block (spec 0004).
 *
 * @see StaffCourseService
 */
class StaffCourseController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StaffCourseService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, StaffCourse $staffCourse): JsonResponse
    {
        try {
            $this->authorize('view', $staffCourse);

            return $this->withPermissions($request->user(), $this->service->withRelations($staffCourse));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffCourse' => $staffCourse->id]);
        }
    }

    public function store(StoreStaffCourseRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', StaffCourse::class);

            $course = $this->service->create($request->toData());

            return $this->withPermissions($request->user(), $course, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateStaffCourseRequest $request, StaffCourse $staffCourse): JsonResponse
    {
        try {
            $this->authorize('update', $staffCourse);

            $course = $this->service->update($staffCourse, $request->toData());

            return $this->withPermissions($request->user(), $course);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffCourse' => $staffCourse->id]);
        }
    }

    public function destroy(StaffCourse $staffCourse): JsonResponse
    {
        try {
            $this->authorize('delete', $staffCourse);

            $this->service->delete($staffCourse);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffCourse' => $staffCourse->id]);
        }
    }

    private function withPermissions(User $actor, StaffCourse $course, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            (new StaffCourseResource($course))->resolve(),
            $this->permissionsBuilder->build($this->authorization->resolve('staff-courses'), $actor, $course),
            $message,
            $status,
        );
    }
}
