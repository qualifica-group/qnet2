<?php

namespace App\Http\Controllers\AgentFiscalProfiles;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AgentFiscalProfiles\AgentFiscalProfileRequest;
use App\Http\Resources\AgentFiscalProfileResource;
use App\Models\AgentFiscalProfile;
use App\Models\User;
use App\Services\AgentFiscalProfileService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `agent-fiscal-profiles` resource (spec 0212). Thin
 * controller: FormRequest validation, server-side authorization
 * (AgentFiscalProfilePolicy), Service call, response.
 *
 * @see AgentFiscalProfileService
 */
class AgentFiscalProfileController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AgentFiscalProfileService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/agent-fiscal-profiles/{agentFiscalProfile}
     */
    public function show(Request $request, AgentFiscalProfile $agentFiscalProfile): JsonResponse
    {
        try {
            $this->authorize('view', $agentFiscalProfile);

            return $this->respond($request->user(), $this->service->detail($agentFiscalProfile));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['agentFiscalProfile' => $agentFiscalProfile->id]);
        }
    }

    /**
     * POST /api/agent-fiscal-profiles
     */
    public function store(AgentFiscalProfileRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', AgentFiscalProfile::class);

            $profile = $this->service->create($request->toData(), $request->user());

            return $this->respond($request->user(), $profile, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/agent-fiscal-profiles/{agentFiscalProfile}
     */
    public function update(AgentFiscalProfileRequest $request, AgentFiscalProfile $agentFiscalProfile): JsonResponse
    {
        try {
            $this->authorize('update', $agentFiscalProfile);

            $profile = $this->service->update($agentFiscalProfile, $request->toData());

            return $this->respond($request->user(), $profile);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['agentFiscalProfile' => $agentFiscalProfile->id]);
        }
    }

    /**
     * DELETE /api/agent-fiscal-profiles/{agentFiscalProfile}
     */
    public function destroy(AgentFiscalProfile $agentFiscalProfile): JsonResponse
    {
        try {
            $this->authorize('delete', $agentFiscalProfile);

            $this->service->delete($agentFiscalProfile);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['agentFiscalProfile' => $agentFiscalProfile->id]);
        }
    }

    private function respond(User $actor, AgentFiscalProfile $profile, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        $permissions = $this->permissionsBuilder->build($this->authorization->resolve('agent-fiscal-profiles'), $actor, $profile);

        return $this->okWithPermissions(
            new AgentFiscalProfileResource($profile, $permissions['fields']),
            $permissions,
            $message,
            $status,
        );
    }
}
