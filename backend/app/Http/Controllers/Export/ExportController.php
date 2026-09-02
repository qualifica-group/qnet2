<?php

namespace App\Http\Controllers\Export;

use App\Enums\ExportFormat;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Export\CreateExportRequest;
use App\Http\Resources\ExportRunResource;
use App\Models\ExportRun;
use App\Models\User;
use App\Services\ExportService;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generic, domain-driven export endpoints (spec 0014). One controller serves
 * every domain with a registered TableDefinition; {domain} resolves it
 * through the TableRegistry (unknown → 404), mirroring
 * App\Http\Controllers\Import\ImportController. Every action re-authorizes
 * via the definition's modelClass() `export` ability (deny → 403); a bound
 * {exportRun} that does not belong to the actor OR whose resource does not
 * match {domain} 404s (never 403), mirroring
 * ImportController::assertOwnedRun.
 *
 * @see ExportService
 */
class ExportController extends BaseApiController
{
    public function __construct(
        private readonly TableRegistry $registry,
        private readonly ExportService $service,
    ) {}

    /**
     * POST /api/exports/{domain} — create the ExportRun (status=processing)
     * and dispatch the async GenerateExportJob.
     */
    public function store(CreateExportRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeExport($definition, $actor);

            $format = ExportFormat::from($request->validated('format'));
            // `opportunityId` (spec 0067, D-5)/`quoteId` (spec 0095, D-8) are
            // frozen alongside the rest of the grid state so
            // GenerateExportJob can re-apply them — the scope must survive
            // the async hop, it cannot live only on this request.
            $state = $request->safe()->only(['columns', 'sortModel', 'filterModel', 'search', 'opportunityId', 'quoteId']);

            $run = $this->service->start($actor, $definition, $state, $format);

            return $this->created(['export_run' => new ExportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/exports/{domain}/{exportRun} — poll the run's status.
     */
    public function show(Request $request, string $domain, ExportRun $exportRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeExport($definition, $request->user());
            $this->assertOwnedRun($exportRun, $request->user(), $domain);

            return $this->ok(['export_run' => new ExportRunResource($exportRun)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['exportRun' => $exportRun->id]);
        }
    }

    /**
     * GET /api/exports/{domain}/{exportRun}/download — stream the generated
     * file. 404 when the run has no completed file yet.
     */
    public function download(Request $request, string $domain, ExportRun $exportRun): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeExport($definition, $request->user());
            $this->assertOwnedRun($exportRun, $request->user(), $domain);
            $this->assertHasFile($exportRun);

            return Storage::disk('local')->download(
                $exportRun->file_path,
                $exportRun->original_filename,
                ['Content-Type' => $exportRun->format->contentType()],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['exportRun' => $exportRun->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeExport(TableDefinition $definition, User $actor): void
    {
        if (! Gate::forUser($actor)->allows('export', $definition->modelClass())) {
            throw new AuthorizationException;
        }
    }

    /**
     * A bound {exportRun} that is not owned by the actor, or whose resource
     * does not match the route {domain}, must never leak cross-user/cross-
     * domain: surfaced as 404 (not 403), identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(ExportRun $exportRun, User $actor, string $domain): void
    {
        if ($exportRun->user_id !== $actor->id || $exportRun->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ExportRun::class, [$exportRun->id]);
        }
    }

    /**
     * @throws ModelNotFoundException when the run has no completed file to
     *                                download yet.
     */
    private function assertHasFile(ExportRun $exportRun): void
    {
        if ($exportRun->file_path === null || ! Storage::disk('local')->exists($exportRun->file_path)) {
            throw (new ModelNotFoundException)->setModel(ExportRun::class, [$exportRun->id]);
        }
    }
}
