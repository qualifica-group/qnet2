<?php

namespace App\Http\Controllers\StaffTrainings;

use App\Enums\HttpStatusEnum;
use App\Enums\StaffTrainingStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\StaffTrainings\StaffTrainingComplianceRequest;
use App\Http\Requests\StaffTrainings\StoreStaffTrainingRequest;
use App\Http\Requests\StaffTrainings\UpdateStaffTrainingRequest;
use App\Http\Resources\StaffTrainingResource;
use App\Models\StaffTraining;
use App\Services\StaffTrainings\StaffTrainingComplianceService;
use App\Services\StaffTrainings\StaffTrainingService;
use App\Services\StaffTrainings\StaffTrainingVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the staff training register (spec 0284). Writes need
 * `staff-trainings.manage` (checked in the FormRequests); reads follow the
 * visibility of StaffTrainingVisibility, a record out of it is a 404.
 */
class StaffTrainingController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly StaffTrainingService $service,
        private readonly StaffTrainingVisibility $visibility,
        private readonly StaffTrainingComplianceService $compliance,
    ) {}

    public function show(Request $request, StaffTraining $staffTraining): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $staffTraining);

            return $this->ok(new StaffTrainingResource($this->service->detail($staffTraining)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }

    public function store(StoreStaffTrainingRequest $request): JsonResponse
    {
        try {
            $training = $this->service->create($request->user(), $request->toAttributes());

            return $this->ok(new StaffTrainingResource($training), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateStaffTrainingRequest $request, StaffTraining $staffTraining): JsonResponse
    {
        try {
            return $this->ok(new StaffTrainingResource($this->service->update($staffTraining, $request->toAttributes())));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }

    public function destroy(Request $request, StaffTraining $staffTraining): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $staffTraining);
            abort_unless($this->visibility->canManage($request->user()), 403, __('You cannot manage staff trainings.'));

            $staffTraining->delete();

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['staffTraining' => $staffTraining->id]);
        }
    }

    /**
     * GET /api/staff-trainings/compliance
     */
    public function compliance(StaffTrainingComplianceRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', StaffTraining::class);

            $filters = $request->validated();

            return $this->ok($this->compliance->build(
                $request->user(),
                isset($filters['status']) ? StaffTrainingStatus::from($filters['status']) : null,
                isset($filters['business_function_id']) ? (int) $filters['business_function_id'] : null,
                isset($filters['staff_course_id']) ? (int) $filters['staff_course_id'] : null,
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
