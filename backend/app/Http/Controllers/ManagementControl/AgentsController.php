<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\AgentsRequest;
use App\Services\ManagementControl\Agents\AgentsBuilder;
use App\Support\Cache\AggregateCache;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/agents (spec 0272). Thin: the request authorizes
 * and validates, the builder computes. The cache key carries the current day
 * with the normalised parameters (stock figures are as of today); every holder
 * of the permission sees the same data.
 */
class AgentsController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-agents:';

    public function __construct(
        private readonly AgentsBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(AgentsRequest $request): JsonResponse
    {
        try {
            $filters = $request->filters();
            $today = CarbonImmutable::today()->toDateString();
            $payload = $this->cache->remember(
                self::CACHE_PREFIX.sha1((string) json_encode([$today, $filters->applied()])),
                fn (): array => $this->builder->handle($filters),
                $request->user(),
            );

            return $this->ok(['applied' => $filters->applied(), 'today' => $today, ...$payload]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}
