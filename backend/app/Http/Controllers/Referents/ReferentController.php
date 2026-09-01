<?php

namespace App\Http\Controllers\Referents;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Referents\StoreReferentRequest;
use App\Http\Requests\Referents\UpdateReferentRequest;
use App\Http\Resources\ReferentResource;
use App\Models\Referent;
use App\Models\User;
use App\Services\ReferentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `referents` resource (spec 0016), backing the
 * backend-driven table row-actions (view/edit/delete) plus create. No
 * for-select endpoint (out of scope — no module selects a referent yet).
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ReferentPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned referent.
 *
 * @see ReferentService
 */
class ReferentController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ReferentService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/referents/{referent} — single referent (view row-action).
     */
    public function show(Request $request, Referent $referent): JsonResponse
    {
        try {
            $this->authorize('view', $referent);

            $permissions = $this->buildPermissions($request->user(), $referent);

            return $this->okWithPermissions(
                new ReferentResource($this->service->loadProfileTree($referent), $permissions['fields']),
                $permissions,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referent' => $referent->id]);
        }
    }

    /**
     * POST /api/referents — create a new referent.
     */
    public function store(StoreReferentRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Referent::class);

            $referent = $this->service->create($request->user(), $request->toData(), $request->toProfile());
            $permissions = $this->buildPermissions($request->user(), $referent);

            return $this->okWithPermissions(
                new ReferentResource($referent, $permissions['fields']),
                $permissions,
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/referents/{referent} — update an existing referent.
     */
    public function update(UpdateReferentRequest $request, Referent $referent): JsonResponse
    {
        try {
            $this->authorize('update', $referent);

            $referent = $this->service->update($request->user(), $referent, $request->toData(), $request->toProfile());
            $permissions = $this->buildPermissions($request->user(), $referent);

            return $this->okWithPermissions(
                new ReferentResource($referent, $permissions['fields']),
                $permissions,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referent' => $referent->id]);
        }
    }

    /**
     * DELETE /api/referents/{referent} — delete a referent.
     */
    public function destroy(Referent $referent): JsonResponse
    {
        try {
            $this->authorize('delete', $referent);

            $this->service->delete($referent);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['referent' => $referent->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Referent $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('referents'), $actor, $model);
    }
}
