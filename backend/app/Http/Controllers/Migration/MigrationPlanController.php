<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Migration\UpdateMigrationPlanRequest;
use App\Migrations\MigrationRegistry;
use App\Services\MigrationPlanService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * The app-wide mass-import plan (spec 0046): which migration sources the
 * "Import all" run executes and in what order. `show`/`update` a singleton,
 * always reconciled against the live registry (App\Services\MigrationPlanService)
 * and decorated with each source's human label. Gated by the route group's
 * `super-admin` middleware alias (EnsureSuperAdmin), like every migrations
 * endpoint.
 */
class MigrationPlanController extends BaseApiController
{
    public function __construct(
        private readonly MigrationPlanService $service,
        private readonly MigrationRegistry $registry,
    ) {}

    /**
     * GET /api/migrations/plan — the reconciled plan (saved or default).
     */
    public function show(): JsonResponse
    {
        try {
            return $this->ok(['plan' => ['sources' => $this->decoratePlan($this->service->current())]]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT /api/migrations/plan — upsert the singleton, return it reconciled.
     */
    public function update(UpdateMigrationPlanRequest $request): JsonResponse
    {
        try {
            $this->service->save($request->plan());

            return $this->ok(['plan' => ['sources' => $this->decoratePlan($this->service->current())]]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * Enrich each plan item with the source's human label and availability
     * (spec 0285, D-5) from the registry. `current()` is reconciled to
     * registered keys only, so resolve() never misses.
     *
     * @param  list<array{source: string, enabled: bool}>  $plan
     * @return list<array{source: string, label: string, enabled: bool, available: bool}>
     */
    private function decoratePlan(array $plan): array
    {
        return array_map(function (array $item): array {
            $source = $this->registry->resolve($item['source']);

            return [
                'source' => $item['source'],
                'label' => $source->label(),
                'enabled' => $item['enabled'],
                'available' => $source->isAvailable(),
            ];
        }, $plan);
    }
}
