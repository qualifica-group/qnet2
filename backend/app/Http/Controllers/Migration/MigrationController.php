<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Migration\MigrationPreviewRequest;
use App\Http\Resources\Migration\MigrationRunResource;
use App\Http\Resources\Migration\MigrationSourceResource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationQuery;
use App\Migrations\MigrationRegistry;
use App\Migrations\MigrationSource;
use App\Models\MigrationRun;
use App\Models\User;
use App\Services\MigrationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Generic, registry-driven external-data migration endpoints (spec 0013).
 * One controller serves every source; {source} resolves the MigrationSource
 * through the registry (unknown -> 404), mirroring App\Http\Controllers\
 * Table\TableController / Import\ImportController. Authorization is a single
 * hard gate applied at the route level (the `super-admin` middleware alias —
 * EnsureSuperAdmin); a bound {migrationRun} that does not belong to the actor
 * OR whose source does not match {source} 404s (never 403), mirroring
 * ImportController::assertOwnedRun.
 *
 * @see MigrationService
 */
class MigrationController extends BaseApiController
{
    public function __construct(
        private readonly MigrationRegistry $registry,
        private readonly MigrationService $service,
    ) {}

    /**
     * GET /api/migrations — the registered sources for the picker, each with
     * its migration state (latest run, last completed migration).
     */
    public function index(): JsonResponse
    {
        try {
            $latestRuns = $this->service->latestRunsBySource();
            $migratedAt = $this->service->migratedAtBySource();

            $sources = array_map(
                fn (MigrationSource $source): MigrationSourceResource => new MigrationSourceResource(
                    $source,
                    $latestRuns->get($source->key()),
                    $migratedAt->get($source->key()),
                ),
                $this->registry->all(),
            );

            return $this->ok(['sources' => $sources]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/columns — the source's preview column
     * catalogue, plus the "expected template" (spec 0013): the external
     * request qnet will issue (method/base_url/path/url, no token) and a
     * copyable sample of the response envelope the parser expects. Fully
     * static — never calls the external system.
     */
    public function columns(string $source): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($source); // 404 if unknown

            return $this->ok([
                'dependencies' => array_map(
                    static fn (MigrationDependency $dependency): array => $dependency->toArray(),
                    $definition->dependencies(),
                ),
                'columns' => $definition->columns(),
                'request' => $this->buildRequestInfo($definition),
                'sample' => $definition->sampleResponse(),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/preview — phase 1, read-only paginated
     * anteprima of the external system.
     */
    public function preview(MigrationPreviewRequest $request, string $source): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($source); // 404 if unknown

            $page = $definition->preview(new MigrationQuery(
                page: $request->pageNumber(),
                perPage: $request->perPageSize(),
            ));

            return $this->ok([
                'rows' => $page->rows,
                'pagination' => [
                    'page' => $page->page,
                    'per_page' => $page->perPage,
                    'total' => $page->total,
                    'has_more' => $page->hasMore,
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/migrations/{source}/import — phase 2, creates the
     * MigrationRun (pending) and dispatches the background job.
     */
    public function import(Request $request, string $source): JsonResponse
    {
        try {
            $this->registry->resolve($source); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();

            $run = $this->service->start($actor, $source);

            return $this->created(['migration_run' => new MigrationRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/migrations/{source}/runs/{migrationRun} — polling: status +
     * counters + report.
     */
    public function run(Request $request, string $source, MigrationRun $migrationRun): JsonResponse
    {
        try {
            $this->registry->resolve($source); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($migrationRun, $actor, $source);

            return $this->ok(['migration_run' => (new MigrationRunResource($migrationRun))->withReport()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['migrationRun' => $migrationRun->id]);
        }
    }

    /**
     * The external GET request qnet will issue for this source's list
     * endpoint (spec 0013 "expected template"): base_url is the SAME,
     * non-secret config key ExternalApiClient reads (the token is never
     * included here).
     *
     * @return array{method: string, base_url: string, path: string, url: string}
     */
    private function buildRequestInfo(MigrationSource $definition): array
    {
        $baseUrl = (string) config('migrations.base_url', '');
        $path = $definition->endpoint();

        return [
            'method' => 'GET',
            'base_url' => $baseUrl,
            'path' => $path,
            'url' => $this->joinUrl($baseUrl, $path),
        ];
    }

    /**
     * Joins a base URL and a relative path with exactly one slash between
     * them; an empty base_url yields the bare path.
     */
    private function joinUrl(string $baseUrl, string $path): string
    {
        $trimmedBase = rtrim($baseUrl, '/');
        $trimmedPath = ltrim($path, '/');

        return $trimmedBase === '' ? $trimmedPath : $trimmedBase.'/'.$trimmedPath;
    }

    /**
     * A bound {migrationRun} that is not owned by the actor, or whose source
     * does not match the route {source}, must never leak cross-user/cross-
     * source: surfaced as 404 (not 403), identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(MigrationRun $migrationRun, User $actor, string $source): void
    {
        if ($migrationRun->user_id !== $actor->id || $migrationRun->source !== $source) {
            throw (new ModelNotFoundException)->setModel(MigrationRun::class, [$migrationRun->id]);
        }
    }
}
