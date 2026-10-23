<?php

declare(strict_types=1);

namespace App\Http\Controllers\Quotes;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Quote;
use App\Services\Commissions\CommissionRecalculationImpact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/quotes/{quote}/commission-recalculation (spec 0308): the counts the
 * "Ricalcola commissioni" dialog shows before the user chooses a mode.
 */
class QuoteCommissionRecalculationController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CommissionRecalculationImpact $impact) {}

    public function __invoke(Quote $quote): JsonResponse
    {
        try {
            $this->authorize('view', $quote);

            return $this->ok($this->impact->forQuote($quote));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }
}
