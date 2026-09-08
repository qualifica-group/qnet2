<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\RequestReportRequest;
use App\Http\Resources\RequestManagementReportRunResource;
use App\Jobs\GenerateRequestManagementReportJob;
use App\Models\ExportRun;
use App\Models\User;
use App\Services\RequestManagement\Report\ReportCategoryAvailabilityResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The request-management report's own create/poll/download endpoints
 * (spec 0106, D-8) — a bespoke controller, deliberately not ExportController
 * (spec 0014): `resource = 'request-management-report'` is not a
 * config/tables.php domain, so the generic `/api/exports/{domain}` routes
 * fail closed on it (scope §out).
 *
 * A bound {exportRun} not owned by the actor, or whose `resource` is not
 * this module's own, 404s (never 403) — mirrors
 * ExportController::assertOwnedRun (AC-003-ter).
 */
class RequestManagementReportController extends BaseApiController
{
    private const string PERMISSION = 'request-management.report';

    private const string RESOURCE = 'request-management-report';

    public function __construct(private readonly ReportCategoryAvailabilityResolver $availability) {}

    /**
     * GET /api/request-management/report/categories — the branches
     * available to the actor (spec 0106 rev-2, D-11/D-12): declared BEFORE
     * `report/{exportRun}` in the route file so this literal segment is
     * never swallowed by the wildcard (routing_trap, AC-036).
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can(self::PERMISSION), 403);

            return $this->ok(['categories' => $this->availability->available($actor)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/request-management/report — create the run and dispatch the
     * async job.
     */
    public function store(RequestReportRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can(self::PERMISSION), 403);

            $dateFrom = (string) $request->validated('date_from');
            $dateTo = (string) $request->validated('date_to');
            /** @var array<int, string> $categoryKeys */
            $categoryKeys = (array) $request->validated('category_keys');
            $rowMode = (string) $request->validated('row_mode');
            $format = ExportFormat::from((string) $request->validated('format'));

            $run = ExportRun::create([
                'resource' => self::RESOURCE,
                'user_id' => $actor->id,
                'status' => ExportStatus::Processing,
                'format' => $format,
                'original_filename' => $this->fileName($dateFrom, $dateTo, $format),
                'state' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'locale' => app()->getLocale(),
                    'category_keys' => $categoryKeys,
                    'row_mode' => $rowMode,
                ],
            ]);

            GenerateRequestManagementReportJob::dispatch($run->id);

            return $this->created(['export_run' => new RequestManagementReportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/request-management/report/{exportRun} — poll the run's
     * status.
     */
    public function show(Request $request, ExportRun $exportRun): JsonResponse
    {
        try {
            abort_unless($request->user()->can(self::PERMISSION), 403);
            $this->assertOwnedRun($exportRun, $request->user());

            return $this->ok(['export_run' => new RequestManagementReportRunResource($exportRun)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['exportRun' => $exportRun->id]);
        }
    }

    /**
     * GET /api/request-management/report/{exportRun}/download — stream the
     * generated file, with the Content-Type of its own format.
     */
    public function download(Request $request, ExportRun $exportRun): StreamedResponse|JsonResponse
    {
        try {
            abort_unless($request->user()->can(self::PERMISSION), 403);
            $this->assertOwnedRun($exportRun, $request->user());
            $this->assertHasFile($exportRun);

            return Storage::disk((string) config('exports.disk'))->download(
                $exportRun->file_path,
                $exportRun->original_filename,
                ['Content-Type' => $exportRun->format->contentType()],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['exportRun' => $exportRun->id]);
        }
    }

    private function fileName(string $dateFrom, string $dateTo, ExportFormat $format): string
    {
        return "request-management-report-{$dateFrom}_{$dateTo}.{$format->extension()}";
    }

    /**
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(ExportRun $exportRun, User $actor): void
    {
        if ($exportRun->user_id !== $actor->id || $exportRun->resource !== self::RESOURCE) {
            throw (new ModelNotFoundException)->setModel(ExportRun::class, [$exportRun->id]);
        }
    }

    /**
     * @throws ModelNotFoundException
     */
    private function assertHasFile(ExportRun $exportRun): void
    {
        $disk = Storage::disk((string) config('exports.disk'));

        if ($exportRun->status !== ExportStatus::Completed || $exportRun->file_path === null || ! $disk->exists($exportRun->file_path)) {
            throw (new ModelNotFoundException)->setModel(ExportRun::class, [$exportRun->id]);
        }
    }
}
