<?php

declare(strict_types=1);

namespace App\Http\Controllers\ManagementControl;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ManagementControl\IncomeStatementRequest;
use App\Services\ManagementControl\IncomeStatementBuilder;
use App\Support\Cache\AggregateCache;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/management-control/income-statement (spec 0268). Thin: the request
 * authorizes and validates, the builder computes. The result is cached per
 * normalised parameters (D-16); every holder of the permission sees the same
 * data (D-7), so the key carries no visibility scope.
 */
class IncomeStatementController extends BaseApiController
{
    private const string CACHE_PREFIX = 'mc-income-statement:';

    public function __construct(
        private readonly IncomeStatementBuilder $builder,
        private readonly AggregateCache $cache,
    ) {}

    public function __invoke(IncomeStatementRequest $request): JsonResponse
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
