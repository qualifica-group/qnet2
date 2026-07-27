<?php

declare(strict_types=1);

namespace App\Http\Controllers\OpportunityWorkflows;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\OpportunityWorkflows\StoreOpportunityWorkflowRequest;
use App\Http\Requests\OpportunityWorkflows\UpdateDefaultStatusesRequest;
use App\Http\Requests\OpportunityWorkflows\UpdateOpportunityWorkflowRequest;
use App\Http\Resources\OpportunityWorkflowResource;
use App\Http\Resources\OpportunityWorkflowStatusResource;
use App\Models\OpportunityWorkflow;
use App\Models\User;
use App\Services\OpportunityWorkflowService;
use App\Support\OpportunityWorkflows\CriterionFieldRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD + default-status-set endpoints for the `opportunity-workflows`
 * configurator (spec 0047, Lane A): view/create/update/delete of a workflow
 * (criteria + statuses in the same request payload), plus the allow-listed
 * criterion fields and the GLOBAL default status set.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (OpportunityWorkflowPolicy), Service call, response. No business logic.
 *
 * @see OpportunityWorkflowService
 */
class OpportunityWorkflowController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly OpportunityWorkflowService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
        private readonly CriterionFieldRegistry $criterionFieldRegistry,
    ) {}

    /**
     * GET /api/opportunity-workflows/{opportunityWorkflow}.
     */
    public function show(OpportunityWorkflow $opportunityWorkflow): JsonResponse
    {
        try {
            $this->authorize('view', $opportunityWorkflow);

            $opportunityWorkflow = $this->service->loadDetail($opportunityWorkflow);

            return $this->okWithPermissions(
                new OpportunityWorkflowResource($opportunityWorkflow),
                $this->buildPermissions($opportunityWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityWorkflow' => $opportunityWorkflow->id]);
        }
    }

    /**
     * POST /api/opportunity-workflows — create a workflow with its criteria
     * and (optional custom) statuses in one request.
     */
    public function store(StoreOpportunityWorkflowRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', OpportunityWorkflow::class);

            $opportunityWorkflow = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new OpportunityWorkflowResource($opportunityWorkflow),
                $this->buildPermissions($opportunityWorkflow),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/opportunity-workflows/{opportunityWorkflow}.
     */
    public function update(UpdateOpportunityWorkflowRequest $request, OpportunityWorkflow $opportunityWorkflow): JsonResponse
    {
        try {
            $this->authorize('update', $opportunityWorkflow);

            $opportunityWorkflow = $this->service->update($opportunityWorkflow, $request->toData());

            return $this->okWithPermissions(
                new OpportunityWorkflowResource($opportunityWorkflow),
                $this->buildPermissions($opportunityWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityWorkflow' => $opportunityWorkflow->id]);
        }
    }

    /**
     * DELETE /api/opportunity-workflows/{opportunityWorkflow} — deletes the
     * workflow and re-resolves every impacted Opportunity (AC-018).
     */
    public function destroy(OpportunityWorkflow $opportunityWorkflow): JsonResponse
    {
        try {
            $this->authorize('delete', $opportunityWorkflow);

            $this->service->delete($opportunityWorkflow);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunityWorkflow' => $opportunityWorkflow->id]);
        }
    }

    /**
     * GET /api/opportunity-workflows/criterion-fields — the allow-listed
     * criterion fields (AC-022), for the criteria editor's field select.
     */
    public function criterionFields(): JsonResponse
    {
        try {
            $this->authorize('opportunity-workflows.view');

            return $this->ok($this->criterionFieldRegistry->allowedFields());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/opportunity-workflows/default-statuses — the GLOBAL default
     * status set (AC-005/AC-010), ordered.
     */
    public function defaultStatuses(): JsonResponse
    {
        try {
            $this->authorize('opportunity-workflows.view');

            return $this->ok(OpportunityWorkflowStatusResource::collection($this->service->defaultStatuses()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/opportunity-workflows/default-statuses — syncs the GLOBAL
     * default status set's custom rows.
     */
    public function updateDefaultStatuses(UpdateDefaultStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('opportunity-workflows.update');

            $statuses = $this->service->syncDefaultStatuses($request->statuses());

            return $this->ok(OpportunityWorkflowStatusResource::collection($statuses));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(?OpportunityWorkflow $model): array
    {
        /** @var User $actor */
        $actor = request()->user();

        return $this->permissionsBuilder->build($this->authorization->resolve('opportunity-workflows'), $actor, $model);
    }
}
