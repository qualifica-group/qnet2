<?php

namespace App\Http\Controllers\UserSkills;

use App\Enums\HttpStatusEnum;
use App\Enums\SkillCoverageStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\UserSkills\StoreUserSkillRequest;
use App\Http\Requests\UserSkills\UpdateUserSkillRequest;
use App\Http\Requests\UserSkills\UserSkillCoverageRequest;
use App\Http\Resources\UserSkillResource;
use App\Models\UserSkill;
use App\Services\Skills\UserSkillCoverageService;
use App\Services\Skills\UserSkillService;
use App\Services\Skills\UserSkillVisibility;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Endpoints of the user skills register (spec 0286). Reads follow the
 * visibility of UserSkillVisibility (404 out of it); writes are checked in the
 * FormRequests (403 when visible but not writable).
 */
class UserSkillController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UserSkillService $service,
        private readonly UserSkillVisibility $visibility,
        private readonly UserSkillCoverageService $coverage,
    ) {}

    public function show(Request $request, UserSkill $userSkill): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $userSkill);

            return $this->ok(new UserSkillResource($this->service->detail($userSkill)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['userSkill' => $userSkill->id]);
        }
    }

    public function store(StoreUserSkillRequest $request): JsonResponse
    {
        try {
            $userSkill = $this->service->create($request->user(), $request->toAttributes());

            return $this->ok(new UserSkillResource($userSkill), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateUserSkillRequest $request, UserSkill $userSkill): JsonResponse
    {
        try {
            return $this->ok(new UserSkillResource($this->service->update($request->user(), $userSkill, $request->toAttributes())));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['userSkill' => $userSkill->id]);
        }
    }

    public function destroy(Request $request, UserSkill $userSkill): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $userSkill);
            $this->visibility->assertCanWritePerson($request->user(), (int) $userSkill->user_id);

            $userSkill->delete();

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['userSkill' => $userSkill->id]);
        }
    }

    /**
     * GET /api/user-skills/coverage
     */
    public function coverage(UserSkillCoverageRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', UserSkill::class);

            $filters = $request->validated();

            return $this->ok($this->coverage->build(
                $request->user(),
                isset($filters['status']) ? SkillCoverageStatus::from($filters['status']) : null,
                isset($filters['business_function_id']) ? (int) $filters['business_function_id'] : null,
                isset($filters['skill_id']) ? (int) $filters['skill_id'] : null,
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
