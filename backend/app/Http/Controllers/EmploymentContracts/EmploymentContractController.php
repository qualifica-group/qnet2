<?php

namespace App\Http\Controllers\EmploymentContracts;

use App\Authorization\UsersAuthorization;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\EmploymentContracts\StoreEmploymentContractRequest;
use App\Http\Resources\EmploymentContractResource;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\EmploymentContracts\EmploymentContractService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Contractual condition history of a user (spec 0272 D-1). Gated by the
 * target user's policy: `users.view` to read, `users.update` to write, and by
 * the `employment.contracts` field of the actor's matrix: visible to read,
 * editable to write (spec 0283 D-4; the store request checks its own).
 *
 * `{employmentContract}` is resolved through the user's own profile instead of
 * implicit binding, so a condition of another person is a 404 by construction
 * (the nested-route scoping rule, backend.md §8).
 */
class EmploymentContractController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly EmploymentContractService $service,
        private readonly UsersAuthorization $authorization,
    ) {}

    /**
     * GET /api/users/{user}/employment-contracts
     */
    public function index(Request $request, User $user): JsonResponse
    {
        try {
            $this->authorize('view', $user);
            $this->assertContractsField($request, $user, editable: false);

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
    public function destroy(Request $request, User $user, int $employmentContract): JsonResponse
    {
        try {
            $this->authorize('update', $user);
            $this->assertContractsField($request, $user, editable: true);

            $this->service->delete($this->profileOf($user)->contracts()->findOrFail($employmentContract));

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['user' => $user->id, 'employmentContract' => $employmentContract]);
        }
    }

    private function assertContractsField(Request $request, User $user, bool $editable): void
    {
        /** @var User $actor */
        $actor = $request->user();
        $field = $this->authorization->fieldPermissions($actor, $user)[UsersAuthorization::CONTRACTS_FIELD];

        abort_unless($editable ? $field->editable : $field->visible, 403, 'This action is unauthorized.');
    }

    private function profileOf(User $user): EmploymentProfile
    {
        return $user->employment ?? abort(404, 'Resource not found.');
    }
}
