<?php

namespace App\Http\Controllers\Migration;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Migration\StartMigrationImportRequest;
use App\Http\Resources\Migration\MassMigrationRunResource;
use App\Models\MassMigrationRun;
use App\Models\User;
use App\Services\MigrationPlanService;
use App\Services\MigrationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The "Import all" run (spec 0046): start a mass import from the saved plan and
 * poll its aggregate + per-source children. Gated by the route group's
 * `super-admin` middleware alias (EnsureSuperAdmin); a bound {massMigrationRun}
 * not owned by the actor 404s (never 403), mirroring MigrationController.
 *
 * @see MigrationService
 */
class MassMigrationController extends BaseApiController
{
    public function __construct(
        private readonly MigrationService $service,
        private readonly MigrationPlanService $planService,
    ) {}

    /**
     * POST /api/migrations/mass-runs — start the mass import from the plan.
     */
    public function store(StartMigrationImportRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            if ($this->planService->enabledSources() === []) {
                return $this->fail(
                    'The mass import plan has no enabled sources.',
                    HttpStatusEnum::UNPROCESSABLE_ENTITY->value,
                );
            }

            $run = $this->service->startMass($actor, $request->updatedSince());

            return $this->created(['mass_migration_run' => new MassMigrationRunResource($run->load('runs'))]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/mass-runs/{massMigrationRun} — polling: aggregate
     * status + ordered children.
     */
    public function show(Request $request, MassMigrationRun $massMigrationRun): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($massMigrationRun, $actor);

            return $this->ok(['mass_migration_run' => new MassMigrationRunResource($massMigrationRun->load('runs'))]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['massMigrationRun' => $massMigrationRun->id]);
        }
    }

    /**
     * A bound {massMigrationRun} not owned by the actor is surfaced as 404 (not
     * 403), identical to an unknown id — never leak another user's run.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(MassMigrationRun $run, User $actor): void
    {
        if ($run->user_id !== $actor->id) {
            throw (new ModelNotFoundException)->setModel(MassMigrationRun::class, [$run->id]);
        }
    }
}
