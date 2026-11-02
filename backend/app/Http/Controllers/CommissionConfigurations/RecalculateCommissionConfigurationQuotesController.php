<?php

declare(strict_types=1);

namespace App\Http\Controllers\CommissionConfigurations;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Commissions\RecalculateCommissionsRequest;
use App\Jobs\RecalculateCommissionConfigurationQuotesJob;
use App\Models\CommissionConfiguration;
use App\Services\Commissions\CommissionRecalculationImpact;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * POST /api/commission-configurations/{commissionConfiguration}/recalculate-quotes
 * (spec 0308, D-6/D-7/D-8): queues the propagation of the rule to the open
 * quotes that use it, with the mode chosen in the controlled-choice dialog.
 */
class RecalculateCommissionConfigurationQuotesController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly CommissionRecalculationImpact $impact) {}

    public function __invoke(RecalculateCommissionsRequest $request, CommissionConfiguration $commissionConfiguration): JsonResponse
    {
        try {
            $this->authorize('update', $commissionConfiguration);

            $actor = $request->user();

            Gate::forUser($actor)->authorize('quotes.update');

            $quotesCount = $this->impact->forConfiguration($commissionConfiguration)['quotes_count'];

            RecalculateCommissionConfigurationQuotesJob::dispatch($commissionConfiguration->id, $request->mode(), $actor->id);

            return $this->ok(
                ['quotes_count' => $quotesCount],
                __('commission_configurations.recalculation_queued'),
                HttpStatusEnum::ACCEPTED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['commissionConfiguration' => $commissionConfiguration->id]);
        }
    }
}
