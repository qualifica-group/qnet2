<?php

namespace App\Http\Controllers\Opportunities;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Opportunities\OpportunityOpenCheckRequest;
use App\Models\Opportunity;
use App\Services\Opportunities\RegistryOpenOpportunityGuard;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/opportunities/open-check (spec 0396, D-6): tells the create flow,
 * before anything is saved, whether the registry already owns an open
 * opportunity that would block a new one. Read-only; gated by the same
 * `opportunities.create` that gates the creation itself.
 *
 * @see RegistryOpenOpportunityGuard::blockingOpportunityFor
 */
class OpportunityOpenCheckController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly RegistryOpenOpportunityGuard $guard) {}

    public function __invoke(OpportunityOpenCheckRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Opportunity::class);

            $blocking = $this->guard->blockingOpportunityFor($request->registryId(), $request->categoryIds());

            return $this->ok([
                'blocking_opportunity' => $blocking === null ? null : ['id' => $blocking->id, 'name' => $blocking->name],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
