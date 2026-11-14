<?php

namespace App\Http\Controllers\Quotes;

use App\Actions\Quotes\CreateQuoteWithOpportunity;
use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Quotes\StoreQuoteWithOpportunityRequest;
use App\Http\Resources\QuoteResource;
use App\Models\Opportunity;
use App\Models\Quote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/quotes/with-opportunity (spec 0396): creates an Opportunity and
 * its first Offerta in one transaction. Both creations are authorized BEFORE
 * any write; the response is the one of POST /api/quotes.
 *
 * @see CreateQuoteWithOpportunity
 */
class QuoteWithOpportunityController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateQuoteWithOpportunity $action,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(StoreQuoteWithOpportunityRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Opportunity::class);
            $this->authorize('create', Quote::class);

            $actor = $request->user();
            $quote = $this->action->handle($request, $actor, $request->opportunityInput(), $request->quoteInput());

            return $this->okWithPermissions(
                new QuoteResource($quote),
                $this->permissionsBuilder->build($this->authorization->resolve('quotes'), $actor, $quote),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
