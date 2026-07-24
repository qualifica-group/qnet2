<?php

namespace App\Http\Controllers\Referents;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Resources\RewardResource;
use App\Models\Referent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/referents/{referent}/rewards — lazy detail endpoint feeding the
 * `rewarded-referents` AG Grid master/detail (spec 0059, D-4). Authorized
 * through the module's OWN `rewarded-referents.view` permission, never
 * `referents.view`: independent permission sets, same precedent as
 * `request-management.*` over `opportunities.*` (RequestManagementPolicy).
 *
 * Thin invokable controller: authz + eager-loaded query + Resource output.
 * No business logic — RewardResource projects `context` live from the
 * origin's current relations (AC-016).
 */
class ReferentRewardsController extends BaseApiController
{
    public function __invoke(Request $request, Referent $referent): JsonResponse
    {
        try {
            abort_unless($request->user()->can('rewarded-referents.view'), 403);

            $rewards = $referent->rewards()
                ->with(RewardResource::eagerLoad())
                ->orderByDesc('assigned_at')
                ->orderByDesc('id')
                ->get();

            return $this->ok(['items' => RewardResource::collection($rewards)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referent' => $referent->id]);
        }
    }
}
