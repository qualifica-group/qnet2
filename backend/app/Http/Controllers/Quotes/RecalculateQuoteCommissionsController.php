<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quotes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Commissions\RecalculateCommissionsRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use App\Services\Commissions\CommissionRecalculationImpact;
use App\Services\Commissions\QuoteCommissionRecalculator;
use App\Services\QuoteService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * POST /api/quotes/{quote}/recalculate-commissions (spec 0308, D-4/D-5):
 * synchronous realignment of one open quote; answers with the same detail
 * (resource + permissions) as GET /api/quotes/{quote}.
 */
class RecalculateQuoteCommissionsController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly QuoteCommissionRecalculator $recalculator,
        private readonly CommissionRecalculationImpact $impact,
        private readonly QuoteService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(RecalculateCommissionsRequest $request, Quote $quote): JsonResponse
    {
        try {
            $this->authorize('update', $quote);

            $actor = $request->user();
            $resource = $this->authorization->resolve('quotes');

            if (! $resource->fieldPermissions($actor, $quote)['commissions']->editable) {
                throw new AuthorizationException(__('You cannot edit the commissions of this quote.'));
            }

            if ($this->impact->isClosed($quote)) {
                throw ValidationException::withMessages([
                    'quote' => [__('quotes.commission_recalculation.closed')],
                ]);
            }

            $this->recalculator->recalculate($quote, $request->mode());

            return $this->okWithPermissions(
                new QuoteResource($this->service->loadDetail($quote)),
                $this->permissionsBuilder->build($resource, $actor, $quote),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }
}
