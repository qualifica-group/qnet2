<?php

namespace App\Http\Controllers\Quotes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Requests\Quotes\UpdateQuoteRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `quotes` resource (spec 0065, MT-05), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (QuotePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned quote.
 *
 * @see QuoteService
 */
class QuoteController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/quotes/{quote} — single quote (view row-action).
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        try {
            $this->authorize('view', $quote);

            return $this->okWithPermissions(
                new QuoteResource($this->service->loadDetail($quote)),
                $this->buildPermissions($request->user(), $quote),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * GET /api/quotes/next-code — the next sequential code (QUO-0001...) as a
     * non-binding suggestion for the create form's auto-fill (D-13). Gated by
     * quotes.create: only an actor who may create needs it.
     */
    public function nextCode(): JsonResponse
    {
        try {
            $this->authorize('create', Quote::class);

            return $this->ok(['code' => $this->service->previewNextCode()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/quotes — create a new quote.
     */
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Quote::class);

            $quote = $this->service->create($request->toData(), $request->user());

            return $this->okWithPermissions(
                new QuoteResource($quote),
                $this->buildPermissions($request->user(), $quote),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/quotes/{quote} — update an existing quote.
     */
    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        try {
            $this->authorize('update', $quote);

            $quote = $this->service->update($quote, $request->toData(), $request->user());

            return $this->okWithPermissions(
                new QuoteResource($quote),
                $this->buildPermissions($request->user(), $quote),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * DELETE /api/quotes/{quote} — delete a quote (its lines cascade, AC-026).
     */
    public function destroy(Quote $quote): JsonResponse
    {
        try {
            $this->authorize('delete', $quote);

            $this->service->delete($quote);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Quote $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('quotes'), $actor, $model);
    }
}
