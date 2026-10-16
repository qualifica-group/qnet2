<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\StructureCostsRequest;
use App\Services\ManagementControl\StructureCostsBuilder;
use App\Support\Cache\AggregateCache;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/structure-costs (spec 0268): the passive costs
 * not tied to any commessa. Thin, cached like the income statement.
 */
class StructureCostsController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-structure-costs:';

    public function __construct(
        private readonly StructureCostsBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(StructureCostsRequest $request): JsonResponse
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
