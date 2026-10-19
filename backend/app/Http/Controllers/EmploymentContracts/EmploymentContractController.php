<?php

namespace App\Http\Controllers\EmploymentContracts;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmploymentContracts\StoreEmploymentContractRequest;
use App\Http\Resources\EmploymentContractResource;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\EmploymentContracts\EmploymentContractService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * Contractual condition history of a user (spec 0272 D-1). Gated by the
 * target user's policy: `users.view` to read, `users.update` to write.
 *
 * `{employmentContract}` is resolved through the user's own profile instead of
 * implicit binding, so a condition of another person is a 404 by construction
 * (the nested-route scoping rule, backend.md §8).
 */
class EmploymentContractController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly EmploymentContractService $service) {}

    /**
     * GET /api/users/{user}/employment-contracts
     */
    public function index(User $user): JsonResponse
    {
        try {
            $this->authorize('view', $user);

            // A person with no employment profile yet simply has no history (spec 0272 rev.2).
            $history = $user->employment === null ? collect() : $this->service->history($user->employment);

            return $this->ok(EmploymentContractResource::collection($history));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * POST /api/users/{user}/employment-contracts
     */
    public function store(StoreEmploymentContractRequest $request, User $user): JsonResponse
    {
        try {
            $contract = $this->service->create($this->profileOf($user), $request->validated());

            return $this->created(new EmploymentContractResource($contract), 'Contract condition created');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id]);
        }
    }

    /**
     * DELETE /api/users/{user}/employment-contracts/{employmentContract}
     */
    public function destroy(User $user, int $employmentContract): JsonResponse
    {
        try {
            $this->authorize('update', $user);

            $this->service->delete($this->profileOf($user)->contracts()->findOrFail($employmentContract));

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id, 'employmentContract' => $employmentContract]);
        }
    }

    private function profileOf(User $user): EmploymentProfile
    {
        return $user->employment ?? abort(404, 'Resource not found.');
    }
}
