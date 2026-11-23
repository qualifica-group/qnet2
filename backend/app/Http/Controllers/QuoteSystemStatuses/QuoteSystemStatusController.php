<?php

declare(strict_types=1);

namespace App\Http\Controllers\QuoteSystemStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\QuoteSystemStatuses\StoreQuoteSystemStatusRequest;
use App\Http\Requests\QuoteSystemStatuses\UpdateQuoteSystemStatusRequest;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Resources\QuoteSystemStatusResource;
use App\Models\QuoteSystemStatus;
use App\Models\User;
use App\Services\QuoteSystemStatuses\QuoteSystemStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + reorder of the quote system statuses (spec 0404 part A). Thin:
 * FormRequest, QuoteSystemStatusPolicy, service, resource.
 */
class QuoteSystemStatusController extends BaseApiController
{
    use AuthorizesRequests;

    private const string RESOURCE = 'quote-system-statuses';

    public function __construct(
        private readonly QuoteSystemStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, QuoteSystemStatus $quoteSystemStatus): JsonResponse
    {
        try {
            $this->authorize('view', $quoteSystemStatus);

            return $this->respond($request->user(), $this->service->loadDetail($quoteSystemStatus));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteSystemStatus' => $quoteSystemStatus->id]);
        }
    }

    public function store(StoreQuoteSystemStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', QuoteSystemStatus::class);

            return $this->respond($request->user(), $this->service->create($request->toData()), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateQuoteSystemStatusRequest $request, QuoteSystemStatus $quoteSystemStatus): JsonResponse
    {
        try {
            $this->authorize('update', $quoteSystemStatus);

            return $this->respond($request->user(), $this->service->update($quoteSystemStatus, $request->toData()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteSystemStatus' => $quoteSystemStatus->id]);
        }
    }

    public function destroy(QuoteSystemStatus $quoteSystemStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $quoteSystemStatus);

            $this->service->delete($quoteSystemStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quoteSystemStatus' => $quoteSystemStatus->id]);
        }
    }

    /**
     * POST /api/quote-system-statuses/reorder — gated on `.update` directly
     * (no single model for a bulk reorder).
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize(self::RESOURCE.'.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (QuoteSystemStatus $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function respond(User $actor, QuoteSystemStatus $status, string $message = 'OK', HttpStatusEnum $code = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            new QuoteSystemStatusResource($status),
            $this->permissionsBuilder->build($this->authorization->resolve(self::RESOURCE), $actor, $status),
            $message,
            $code,
        );
    }
}
