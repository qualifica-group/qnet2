<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuoteWorkflows;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteWorkflows\StoreQuoteWorkflowRequest;
use App\Http\Requests\QuoteWorkflows\UpdateDefaultStatusesRequest;
use App\Http\Requests\QuoteWorkflows\UpdateQuoteWorkflowRequest;
use App\Http\Resources\QuoteWorkflowResource;
use App\Http\Resources\QuoteWorkflowStatusResource;
use App\Models\QuoteWorkflow;
use App\Models\User;
use App\Services\QuoteWorkflowService;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * CRUD + default-status-set endpoints for the `quote-workflows` configurator
 * (spec 0047, moved onto the Offerta by spec 0083 D-6): view/create/update/
 * delete of a workflow (criteria + statuses in the same request payload),
 * plus the allow-listed criterion fields and the GLOBAL default status set.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (QuoteWorkflowPolicy), Service call, response. No business logic.
 *
 * @see QuoteWorkflowService
 */
class QuoteWorkflowController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteWorkflowService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
        private readonly QuoteCriterionFieldRegistry $criterionFieldRegistry,
    ) {}

    /**
     * GET /api/quote-workflows/{quoteWorkflow}.
     */
    public function show(QuoteWorkflow $quoteWorkflow): JsonResponse
    {
        try {
            $this->authorize('view', $quoteWorkflow);

            $quoteWorkflow = $this->service->loadDetail($quoteWorkflow);

            return $this->okWithPermissions(
                new QuoteWorkflowResource($quoteWorkflow),
                $this->buildPermissions($quoteWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteWorkflow' => $quoteWorkflow->id]);
        }
    }

    /**
     * POST /api/quote-workflows — create a workflow with its criteria and
     * (optional custom) statuses in one request.
     */
    public function store(StoreQuoteWorkflowRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', QuoteWorkflow::class);

            $quoteWorkflow = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new QuoteWorkflowResource($quoteWorkflow),
                $this->buildPermissions($quoteWorkflow),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/quote-workflows/{quoteWorkflow}.
     */
    public function update(UpdateQuoteWorkflowRequest $request, QuoteWorkflow $quoteWorkflow): JsonResponse
    {
        try {
            $this->authorize('update', $quoteWorkflow);

            $quoteWorkflow = $this->service->update($quoteWorkflow, $request->toData());

            return $this->okWithPermissions(
                new QuoteWorkflowResource($quoteWorkflow),
                $this->buildPermissions($quoteWorkflow),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteWorkflow' => $quoteWorkflow->id]);
        }
    }

    /**
     * DELETE /api/quote-workflows/{quoteWorkflow} — deletes the workflow and
     * re-resolves every impacted Quote (AC-018).
     */
    public function destroy(QuoteWorkflow $quoteWorkflow): JsonResponse
    {
        try {
            $this->authorize('delete', $quoteWorkflow);

            $this->service->delete($quoteWorkflow);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteWorkflow' => $quoteWorkflow->id]);
        }
    }

    /**
     * GET /api/quote-workflows/criterion-fields — the allow-listed criterion
     * fields (AC-016), for the criteria editor's field select.
     */
    public function criterionFields(): JsonResponse
    {
        try {
            $this->authorize('quote-workflows.view');

            return $this->ok($this->criterionFieldRegistry->allowedFields());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/quote-workflows/default-statuses — the GLOBAL default status
     * set (AC-005/AC-010), ordered.
     */
    public function defaultStatuses(): JsonResponse
    {
        try {
            $this->authorize('quote-workflows.view');

            return $this->ok(QuoteWorkflowStatusResource::collection($this->service->defaultStatuses()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/quote-workflows/default-statuses — syncs the GLOBAL default
     * status set's custom rows.
     */
    public function updateDefaultStatuses(UpdateDefaultStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('quote-workflows.update');

            $statuses = $this->service->syncDefaultStatuses($request->statuses());

            return $this->ok(QuoteWorkflowStatusResource::collection($statuses));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(?QuoteWorkflow $model): array
    {
        /** @var User $actor */
        $actor = request()->user();

        return $this->permissionsBuilder->build($this->authorization->resolve('quote-workflows'), $actor, $model);
    }
}
