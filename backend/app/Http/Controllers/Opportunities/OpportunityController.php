<?php

namespace App\Http\Controllers\Opportunities;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Opportunities\StoreOpportunityRequest;
use App\Http\Requests\Opportunities\UpdateOpportunityRequest;
use App\Http\Requests\RequestManagement\RequestFormContextRequest;
use App\Http\Resources\OpportunityResource;
use App\Http\Resources\RequestFormContextResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\RequestManagement\RequestFormContextResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `opportunities` resource (spec 0040), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (OpportunityPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned opportunity.
 *
 * @see OpportunityService
 */
class OpportunityController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OpportunityService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
        private readonly RequestFormContextResolver $formContextResolver,
    ) {}

    /**
     * POST /api/opportunities/form-context (user directive 2026-08-05) — the
     * dynamic attributes and their layout for the criteria typed so far, so
     * the CREATE form can render "Informazioni aggiuntive" before anything is
     * persisted, exactly as the request-management create form does.
     *
     * Deliberately the SAME resolver, the same request contract and the same
     * response shape as POST /api/request-management/form-context, not a
     * parallel implementation: the applicable set is an OPPORTUNITY-level
     * concept (spec 0049 D-4 — App\RequestManagement only happens to be where
     * the resolver lives), so two copies could only ever drift into two
     * different sets for the same product lines.
     *
     * Read-only despite the verb: the criteria are a collection of objects,
     * which has no sane query-string encoding. Gated on `opportunities.create`
     * — the actor is filling THIS module's create form; the request-management
     * ability is unrelated and may well be absent.
     */
    public function formContext(RequestFormContextRequest $request): JsonResponse
    {
        try {
            abort_unless($request->user()->can('opportunities.create'), 403);

            return $this->ok(new RequestFormContextResource(
                $this->formContextResolver->resolve($request->sourceId(), $request->productLines()),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/opportunities/{opportunity} — single opportunity (view row-action).
     */
    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $this->authorize('view', $opportunity);

            return $this->okWithPermissions(
                new OpportunityResource($this->service->loadDetail($opportunity)),
                $this->buildPermissions($request->user(), $opportunity),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * POST /api/opportunities — create a new opportunity.
     */
    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Opportunity::class);

            $opportunity = $this->service->create($request->toData(), $request->user());

            return $this->okWithPermissions(
                new OpportunityResource($opportunity),
                $this->buildPermissions($request->user(), $opportunity),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/opportunities/{opportunity} — update an existing opportunity.
     */
    public function update(UpdateOpportunityRequest $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $this->authorize('update', $opportunity);

            $opportunity = $this->service->update($opportunity, $request->toData(), $request->user());

            return $this->okWithPermissions(
                new OpportunityResource($opportunity),
                $this->buildPermissions($request->user(), $opportunity),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * DELETE /api/opportunities/{opportunity} — delete an opportunity.
     */
    public function destroy(Opportunity $opportunity): JsonResponse
    {
        try {
            $this->authorize('delete', $opportunity);

            $this->service->delete($opportunity);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Opportunity $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('opportunities'), $actor, $model);
    }
}
