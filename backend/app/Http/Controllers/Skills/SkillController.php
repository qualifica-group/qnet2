<?php

namespace App\Http\Controllers\Skills;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Skills\StoreSkillRequest;
use App\Http\Requests\Skills\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use App\Models\User;
use App\Services\SkillService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `skills` resource (spec 0286 D-2). Thin
 * controller: validation (FormRequest), authorization (SkillPolicy),
 * Service call, response with the `permissions` block (spec 0004).
 *
 * @see SkillService
 */
class SkillController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly SkillService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, Skill $skill): JsonResponse
    {
        try {
            $this->authorize('view', $skill);

            return $this->withPermissions($request->user(), $this->service->withRelations($skill));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['skill' => $skill->id]);
        }
    }

    public function store(StoreSkillRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Skill::class);

            $skill = $this->service->create($request->toData());

            return $this->withPermissions($request->user(), $skill, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateSkillRequest $request, Skill $skill): JsonResponse
    {
        try {
            $this->authorize('update', $skill);

            $skill = $this->service->update($skill, $request->toData());

            return $this->withPermissions($request->user(), $skill);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['skill' => $skill->id]);
        }
    }

    public function destroy(Skill $skill): JsonResponse
    {
        try {
            $this->authorize('delete', $skill);

            $this->service->delete($skill);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['skill' => $skill->id]);
        }
    }

    private function withPermissions(User $actor, Skill $skill, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            (new SkillResource($skill))->resolve(),
            $this->permissionsBuilder->build($this->authorization->resolve('skills'), $actor, $skill),
            $message,
            $status,
        );
    }
}
