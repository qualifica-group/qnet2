<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\AccountFlowsRequest;
use App\Services\ManagementControl\Accounts\AccountFlowsBuilder;
use App\Support\Cache\AggregateCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/account-flows (spec 0271). Thin: the request
 * authorizes and validates, the builder computes. The cache key carries the
 * current day with the normalised parameters (an open-ended period moves with
 * the data); every holder of the permission sees the same data.
 */
class AccountFlowsController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-account-flows:';

    public function __construct(
        private readonly AccountFlowsBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(AccountFlowsRequest $request): JsonResponse
    {
        try {
            $filters = $request->filters();
            $today = CarbonImmutable::today()->toDateString();
            $payload = $this->cache->remember(
                self::CACHE_PREFIX.sha1((string) json_encode([$today, $filters->applied()])),
                fn (): array => $this->builder->handle($filters),
                $request->user(),
            );

            return $this->ok(['applied' => $filters->applied(), ...$payload]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
