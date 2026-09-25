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
use App\RequestManagement\RequestModule;
use App\Services\RequestManagement\Report\ReportCategoryAvailabilityResolver;
use App\Services\RequestManagement\Report\ReportOperatorAvailabilityResolver;
use App\Services\RequestManagement\Report\ReportSiteAvailabilityResolver;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * The request-management report's own create/poll/download endpoints
 * (spec 0106, D-8) — a bespoke controller, deliberately not ExportController
 * (spec 0014): `resource = '{module}-report'` is not a config/tables.php
 * domain, so the generic `/api/exports/{domain}` routes fail closed on it
 * (scope §out).
 *
 * A bound {exportRun} not owned by the actor, or whose `resource` is not
 * this module's own, 404s (never 403) — mirrors
 * ExportController::assertOwnedRun (AC-003-ter).
 *
 * Spec 0130: every route below carries its own RequestModule
 * (routes/api/request-management.php's own loop), resolved here via
 * RequestModule::fromRequest() — never from client input. The gate is
 * `$module->permission('report')` and the run's own `resource` is
 * `"{$module->value}-report"`: for RequestModule::Requests that is the
 * SAME `'request-management-report'` literal every existing test asserts
 * (parity), and for Enrollees it is `'enrollee-management-report'` — an
 * ExportRun created by one module's `report` route can never be read back
 * through the other (assertOwnedRun below), which is what keeps AC-009's
 * "an exportRun created by a module is not readable by the other" true.
 */
class RequestManagementReportController extends BaseApiController
{
    public function __construct(
        private readonly ReportCategoryAvailabilityResolver $availability,
        private readonly ReportOperatorAvailabilityResolver $operatorAvailability,
        private readonly ReportSiteAvailabilityResolver $siteAvailability,
    ) {}

    /**
     * GET /api/request-management/report/categories — the branches
     * available to the actor (spec 0106 rev-2, D-11/D-12): declared BEFORE
     * `report/{exportRun}` in the route file so this literal segment is
     * never swallowed by the wildcard (routing_trap, AC-036).
     */
    public function categories(Request $request): JsonResponse
    {
        try {
            $module = RequestModule::fromRequest($request);
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can($module->permission('report')), 403);

            return $this->ok(['categories' => $this->availability->available($actor, $module)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/request-management/report/operators — the GA2 Operatore the
     * actor may filter by (spec 0108, D-6). Same literal-segment-before-
     * wildcard rule as report/categories above (AC-013).
     */
    public function operators(Request $request): JsonResponse
    {
        try {
            $module = RequestModule::fromRequest($request);
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can($module->permission('report')), 403);

            return $this->ok(['operators' => $this->operatorAvailability->available($actor, $module)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/request-management/report/sites — the Sedi operative the
     * actor may filter by (spec 0112, D-10). Same literal-segment-before-
     * wildcard rule as report/categories and report/operators above
     * (AC-010).
     */
    public function sites(Request $request): JsonResponse
    {
        try {
            $module = RequestModule::fromRequest($request);
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can($module->permission('report')), 403);

            return $this->ok(['sites' => $this->siteAvailability->available($actor, $module)]);
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
            $module = RequestModule::fromRequest($request);
            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can($module->permission('report')), 403);

            $dateFrom = $request->dateFrom();
            $dateTo = $request->dateTo();
            /** @var array<int, string> $categoryKeys */
            $categoryKeys = (array) $request->validated('category_keys');
            $rowMode = (string) $request->validated('row_mode');
            $operatorKeys = $request->operatorKeys();
            $siteKeys = $request->siteKeys();
            $format = ExportFormat::from((string) $request->validated('format'));

            $run = ExportRun::create([
                'resource' => $this->resource($module),
                'user_id' => $actor->id,
                'status' => ExportStatus::Processing,
                'format' => $format,
                'original_filename' => $this->fileName($module, $dateFrom, $dateTo, $format),
                'state' => [
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'locale' => app()->getLocale(),
                    'category_keys' => $categoryKeys,
                    'row_mode' => $rowMode,
                    // Written ONLY when the actor filtered (spec 0108 D-2): an
                    // absent key is what the job reads as "every operator", the
                    // very shape every run frozen before this spec already has.
                    ...($operatorKeys === null ? [] : ['operator_keys' => $operatorKeys]),
                    // Same shape, same reason, for the Sede selection (spec
                    // 0112 D-4/AC-014).
                    ...($siteKeys === null ? [] : ['site_keys' => $siteKeys]),
                    // Spec 0130: written ONLY for a non-default module — a run
                    // frozen before this spec, or created under
                    // request-management, has no such key, and the job reads
                    // its absence as RequestModule::Requests (same optional-key
                    // convention as operator_keys/site_keys above).
                    ...($module === RequestModule::Requests ? [] : ['module' => $module->value]),
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
            $module = RequestModule::fromRequest($request);
            abort_unless($request->user()->can($module->permission('report')), 403);
            $this->assertOwnedRun($exportRun, $request->user(), $module);

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
            $module = RequestModule::fromRequest($request);
            abort_unless($request->user()->can($module->permission('report')), 403);
            $this->assertOwnedRun($exportRun, $request->user(), $module);
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

    /**
     * `{resource}-{from}_{to}`, or the open-bound variants of spec 0169 D-4:
     * `-from-{from}`, `-to-{to}`, and the bare resource when neither is set.
     */
    private function fileName(RequestModule $module, ?string $dateFrom, ?string $dateTo, ExportFormat $format): string
    {
        $period = match (true) {
            $dateFrom !== null && $dateTo !== null => "-{$dateFrom}_{$dateTo}",
            $dateFrom !== null => "-from-{$dateFrom}",
            $dateTo !== null => "-to-{$dateTo}",
            default => '',
        };

        return "{$this->resource($module)}{$period}.{$format->extension()}";
    }

    /**
     * `"{$module->value}-report"` — for RequestModule::Requests the SAME
     * `'request-management-report'` literal every existing test asserts.
     */
    private function resource(RequestModule $module): string
    {
        return "{$module->value}-report";
    }

    /**
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(ExportRun $exportRun, User $actor, RequestModule $module): void
    {
        if ($exportRun->user_id !== $actor->id || $exportRun->resource !== $this->resource($module)) {
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
