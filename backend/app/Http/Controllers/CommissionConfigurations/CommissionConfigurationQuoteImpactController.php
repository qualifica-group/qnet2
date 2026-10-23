<?php

declare(strict_types=1);

namespace App\Http\Controllers\CommissionConfigurations;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\CommissionConfiguration;
use App\Services\Commissions\CommissionRecalculationImpact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/commission-configurations/{commissionConfiguration}/quote-impact
 * (spec 0308, D-6/D-8): the open quotes a saved rule would realign, and
 * whether the actor may do it (`update` on the rule AND `quotes.update`).
 */
class CommissionConfigurationQuoteImpactController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CommissionRecalculationImpact $impact) {}

    public function __invoke(Request $request, CommissionConfiguration $commissionConfiguration): JsonResponse
    {
        try {
            $this->authorize('view', $commissionConfiguration);

            $actor = $request->user();

            return $this->ok([
                ...$this->impact->forConfiguration($commissionConfiguration),
                'can_apply' => $actor->can('update', $commissionConfiguration) && $actor->can('quotes.update'),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionConfiguration' => $commissionConfiguration->id]);
        }
    }
}
