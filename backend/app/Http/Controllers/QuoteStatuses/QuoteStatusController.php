<?php

namespace App\Http\Controllers\QuoteStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteStatuses\StoreQuoteStatusRequest;
use App\Http\Requests\QuoteStatuses\UpdateQuoteStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\QuoteStatusResource;
use App\Models\QuoteStatus;
use App\Models\User;
use App\Services\QuoteStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `quote-statuses` resource (spec 0065), backing the
 * backend-driven table row-actions (view/edit/delete) plus create. A plain
 * clone of OpportunityStatusController.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (QuoteStatusPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see QuoteStatusService
 */
class QuoteStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/quote-statuses/{quoteStatus} — single quote status (view
     * row-action).
     */
    public function show(Request $request, QuoteStatus $quoteStatus): JsonResponse
    {
        try {
            $this->authorize('view', $quoteStatus);

            $quoteStatus = $this->service->loadDetail($quoteStatus);

            return $this->okWithPermissions(
                new QuoteStatusResource($quoteStatus),
                $this->buildPermissions($request->user(), $quoteStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteStatus' => $quoteStatus->id]);
        }
    }

    /**
     * POST /api/quote-statuses — create a new quote status.
     */
    public function store(StoreQuoteStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', QuoteStatus::class);

            $quoteStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new QuoteStatusResource($quoteStatus),
                $this->buildPermissions($request->user(), $quoteStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/quote-statuses/{quoteStatus} — update an existing quote
     * status.
     */
    public function update(UpdateQuoteStatusRequest $request, QuoteStatus $quoteStatus): JsonResponse
    {
        try {
            $this->authorize('update', $quoteStatus);

            $quoteStatus = $this->service->update($quoteStatus, $request->toData());

            return $this->okWithPermissions(
                new QuoteStatusResource($quoteStatus),
                $this->buildPermissions($request->user(), $quoteStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteStatus' => $quoteStatus->id]);
        }
    }

    /**
     * DELETE /api/quote-statuses/{quoteStatus} — delete a quote status (409
     * if referenced by a Quote).
     */
    public function destroy(QuoteStatus $quoteStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $quoteStatus);

            $this->service->delete($quoteStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteStatus' => $quoteStatus->id]);
        }
    }

    /**
     * POST /api/quote-statuses/reorder — resequence the custom rows. Gated
     * on `quote-statuses.update` directly (no single Model instance exists
     * for a bulk reorder, so there is no Policy `update($user, $model)` to
     * delegate to — mirrors ExportController's `export` ability check).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('quote-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (QuoteStatus $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?QuoteStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('quote-statuses'), $actor, $model);
    }
}
