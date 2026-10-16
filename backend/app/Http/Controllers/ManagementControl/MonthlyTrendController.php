<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\MonthlyTrendRequest;
use App\Services\ManagementControl\Trend\MonthlyTrendBuilder;
use App\Support\Cache\AggregateCache;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/monthly-trend (spec 0270). Thin: the request
 * authorizes and validates, the builder computes. The result is cached per
 * normalised parameters; every holder of the permission sees the same data
 * (D-7 of spec 0268), so the key carries no visibility scope.
 */
class MonthlyTrendController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-monthly-trend:';

    public function __construct(
        private readonly MonthlyTrendBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(MonthlyTrendRequest $request): JsonResponse
    {
        try {
            $filters = $request->filters();
            $payload = $this->cache->remember(
                self::CACHE_PREFIX.sha1((string) json_encode($filters->applied())),
                fn (): array => $this->builder->handle($filters),
                $request->user(),
            );

            return $this->ok(['applied' => $filters->applied(), ...$payload]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
