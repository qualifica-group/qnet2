<?php

declare(strict_types=1);

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Treasury\TreasuryOverviewRequest;
use App\Services\Treasury\TreasuryOverviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/treasury-overview (spec 0362, "Quadro finanziario"). Thin: the
 * request authorizes and validates, the builder computes a snapshot at today.
 */
class TreasuryOverviewController extends BaseApiController
{
    public function __construct(private readonly TreasuryOverviewBuilder $builder) {}

    public function __invoke(TreasuryOverviewRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->builder->handle($request->companyId(), CarbonImmutable::today()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
