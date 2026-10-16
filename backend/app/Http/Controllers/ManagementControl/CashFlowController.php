<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\CashFlowRequest;
use App\Services\ManagementControl\CashFlow\CashFlowBuilder;
use App\Support\Cache\AggregateCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/cash-flow (spec 0270). Thin: the request
 * authorizes and validates, the builder computes. The payload is a snapshot at
 * the current day, so the cache key carries the day with the normalised
 * parameters; every holder of the permission sees the same data.
 */
class CashFlowController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-cash-flow:';

    public function __construct(
        private readonly CashFlowBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(CashFlowRequest $request): JsonResponse
    {
        try {
            $filters = $request->filters();
            $today = CarbonImmutable::today()->toDateString();
            $payload = $this->cache->remember(
                self::CACHE_PREFIX.sha1((string) json_encode([$today, $filters->applied()])),
                fn (): array => $this->builder->handle($filters, $today),
                $request->user(),
            );

            return $this->ok(['applied' => $filters->applied(), ...$payload]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
