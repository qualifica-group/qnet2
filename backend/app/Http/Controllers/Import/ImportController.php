<?php

namespace App\Http\Controllers\Import;

use App\Exceptions\Import\ImportConversionNotReadyException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\Import\Concerns\GuardsImportRequests;
use App\Http\Requests\Import\BulkAssignRequest;
use App\Http\Requests\Import\ConfigureImportRequest;
use App\Http\Requests\Import\ConfirmImportRequest;
use App\Http\Requests\Import\ResolveImportRowRequest;
use App\Http\Requests\Import\UpdateImportRowRequest;
use App\Http\Requests\Import\UploadImportRequest;
use App\Http\Resources\ImportRunResource;
use App\Http\Resources\ImportRunRowResource;
use App\Imports\ImportRegistry;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\ImportService;
use App\Support\Import\ImportRunPayloadBuilder;
use App\Support\Import\ImportRunSummaryBuilder;
use App\Support\Import\ReviewRowsQuery;
use App\Support\Import\StagedRowReviser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generic, domain-driven import endpoints (spec 0012, extended by the
 * unified wizard flow spec 0033). One controller serves every domain;
 * {domain} resolves through the registry (unknown → 404), mirroring
 * App\Http\Controllers\Table\TableController.
 *
 * Every action is gated by the domain's `{resource}.import` ability — for the
 * only registered domain (`leads`) that is `leads.import`. `$this->authorize()`
 * against ImportRun resolves through ImportRunPolicy, which itself checks
 * `leads.import` (the former dedicated `import-runs.*` module set was a
 * duplicate, removed 2026-07-17); writes additionally call `authorizeImport()`
 * (the definition's own `{resource}.import`), the same ability — a redundant
 * but harmless second gate on template/upload/configure/updateRow/confirm.
 * A bound {importRun} that does not belong to the actor OR whose resource
 * does not match {domain} 404s (never 403) — assertOwnedRun() always runs
 * BEFORE any gate, so ownership/domain mismatch never leaks as a 403,
 * mirroring TableFilterViewController::assertBelongsToDomain.
 *
 * upload()/show()/confirm() are BRANCH-AWARE: a "wizard" definition
 * (isWizardDefinition() — non-empty globalConfig(), supportsExtraFields(), or
 * any dedupModes() beyond the legacy create_only) drives the analyze ->
 * configure -> stage -> review -> confirm flow via ImportService::
 * startAnalyze()/confirmStaged(); every other (legacy) definition keeps the
 * original two-phase start()/confirm() flow untouched — the two never share
 * a run (status alone tells them apart).
 *
 * @see ImportService
 */
class ImportController extends BaseApiController
{
    use AuthorizesRequests;
    use GuardsImportRequests;

    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $service,
        private readonly ImportRunPayloadBuilder $payloadBuilder,
        private readonly ImportRunSummaryBuilder $summaryBuilder,
        private readonly ReviewRowsQuery $reviewRowsQuery,
        private readonly StagedRowReviser $rowReviser,
    ) {}

    /**
     * GET /api/imports/{domain}/template — downloadable CSV template, header =
     * the definition's declared columns, in order.
     */
    public function template(Request $request, string $domain): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorize('create', ImportRun::class);
            $this->authorizeImport($definition, $request->user());

            $columns = $definition->columnIds();

            return response()->streamDownload(function () use ($columns): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $columns);
                fclose($handle);
            }, "{$domain}-import-template.csv", ['Content-Type' => 'text/csv']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/imports/{domain} — paginated history of the actor's OWN runs
     * for this domain (spec 0033, AC-018).
     */
    public function index(Request $request, string $domain): JsonResponse
    {
        try {
            $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorize('viewAny', ImportRun::class);

            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(self::MAX_LIMIT, max(1, (int) $request->query('per_page', 15)));

            $paginator = ImportRun::query()
                ->where('user_id', $actor->id)
                ->where('resource', $domain)
                ->latest('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->paginatedResponse(
                items: ImportRunResource::collection($paginator->items()),
                total: $paginator->total(),
                offset: ($page - 1) * $perPage,
                limit: $perPage,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/imports/{domain} — store the uploaded file and create the
     * ImportRun. A wizard definition (e.g. `leads`) starts analyzing
     * (spec 0033, AC-007); a legacy definition starts the original dry-run
     * validation (spec 0012), untouched.
     */
    public function upload(UploadImportRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorize('create', ImportRun::class);
            $this->authorizeImport($definition, $actor);

            $run = $this->isWizardDefinition($definition)
                ? $this->service->startAnalyze($actor, $definition, $request->file('file'))
                : $this->service->start($actor, $definition, $request->file('file'));

            return $this->created(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun} — poll the run's status. For a
     * wizard run this is enriched (spec 0033) with the definition's mapping
     * catalogue and a LIVE-recomputed `suggested_mapping` (diffable against
     * whatever the user has since edited into `column_mapping`); for a
     * legacy run these fields are simply the AbstractImportDefinition
     * defaults (empty/null), harmless additions to the frozen spec-0012 shape.
     */
    public function show(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('view', $importRun);

            return $this->ok([
                'import_run' => $this->payloadBuilder->build($definition, $importRun),
                'preview' => $importRun->preview,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * PUT /api/imports/{domain}/{importRun}/configure — persist the wizard's
     * mapping/global config/dedup strategy and dispatch staging (spec 0033,
     * AC-008). Valid only from `configuring` (ImportService::configure aborts
     * 422 otherwise).
     */
    public function configure(ConfigureImportRequest $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $request->user());

            $data = $request->safe()->only(['column_mapping', 'global_config', 'dedup_strategy']);

            $run = $this->service->configure(
                $importRun,
                $data['column_mapping'],
                $data['global_config'] ?? [],
                $data['dedup_strategy'],
            );

            return $this->ok(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * POST /api/imports/{domain}/{importRun}/rows — SSRM page of the run's
     * staged rows (spec 0033, AC-016), valid only from `reviewing`. Sort/
     * filter/search are ALL delegated to ReviewRowsQuery's allow-list — never
     * built from raw input here.
     */
    public function rows(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('view', $importRun);
            $this->assertReadableStatus($importRun);

            $params = $request->validate([
                'startRow' => ['sometimes', 'integer', 'min:0'],
                'endRow' => ['sometimes', 'integer', 'min:0'],
                'sortModel' => ['sometimes', 'array'],
                'filterModel' => ['sometimes', 'array'],
                'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            ]);

            $fieldIds = array_map(static fn (array $field): string => $field['id'], $definition->fields());
            $result = $this->reviewRowsQuery->paginate($importRun, $fieldIds, $params);

            return $this->paginatedResponse(
                items: ImportRunRowResource::collection($result['items']),
                total: $result['total'],
                offset: $result['offset'],
                limit: $result['limit'],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * PATCH /api/imports/{domain}/{importRun}/rows/{row} — inline-edit one
     * staged row (spec 0033, AC-017; extended by spec 0038 with the optional
     * `geo` pin, spec 0094 with the per-row `product_ids` override), valid
     * only from `reviewing`. Re-runs the SAME StagedRowBuilder pipeline
     * (recognizers + validateRow + resolveDuplicate) the original staging
     * used, via StagedRowReviser, then recomputes the run's counters from
     * the database.
     */
    public function updateRow(UpdateImportRowRequest $request, string $domain, ImportRun $importRun, ImportRunRow $row): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($importRun, $actor, $domain);
            $this->assertRowBelongsToRun($row, $importRun);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $actor);
            $this->assertReviewing($importRun);

            $data = $request->safe()->only(['values', 'geo', 'operator_id', 'operational_site_id', 'product_ids', 'campaign_id']);
            $operatorIdSubmitted = array_key_exists('operator_id', $data);
            $siteIdSubmitted = array_key_exists('operational_site_id', $data);
            $productIdsSubmitted = array_key_exists('product_ids', $data);
            $campaignIdSubmitted = array_key_exists('campaign_id', $data);

            $updated = $this->rowReviser->revise(
                definition: $definition,
                actor: $actor,
                run: $importRun,
                row: $row,
                editedValues: $data['values'] ?? null,
                geo: $data['geo'] ?? null,
                operatorIdSubmitted: $operatorIdSubmitted,
                operatorId: $operatorIdSubmitted ? $data['operator_id'] : null,
                siteIdSubmitted: $siteIdSubmitted,
                siteId: $siteIdSubmitted ? $data['operational_site_id'] : null,
                productIdsSubmitted: $productIdsSubmitted,
                productIds: $productIdsSubmitted ? $data['product_ids'] : null,
                campaignIdSubmitted: $campaignIdSubmitted,
                campaignId: $campaignIdSubmitted ? $data['campaign_id'] : null,
            );
            $this->service->recomputeCounts($importRun->fresh());

            return $this->ok([
                'row' => new ImportRunRowResource($updated),
                'counts' => $this->summaryBuilder->counts($importRun->fresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id, 'row' => $row->id]);
        }
    }

    /**
     * PATCH /api/imports/{domain}/{importRun}/rows/assign — bulk-assign an
     * operator, an operational site and/or the "Prodotti di interesse" to
     * many staged rows in a single mass UPDATE (spec 0045 bulk increment,
     * extended to a COMBINED operator+site assignment, then to bulk
     * `product_ids`), distinct from the single-row PATCH .../rows/{row}
     * above, valid only from `reviewing`. AG Grid
     * `getServerSideSelectionState()` semantics: `row_ids` are the rows to
     * target, or to EXCLUDE when `select_all` is true — BulkAssignRequest
     * already validated every id belongs to this run (anti-IDOR) and every
     * product sits inside the run's campaign coverage. Pure
     * operator/site/product assignment, never gated by `opportunities.create`
     * (that gate is confirm()'s own, spec 0045).
     *
     * `skipped` (spec 0110, additive): the targeted rows `mode=balanced`
     * deliberately left without an operator because nobody at the Sede is
     * competent for them. Always 0 with `mode=single`.
     */
    public function bulkAssign(BulkAssignRequest $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($importRun, $actor, $domain);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $actor);
            $this->assertReviewing($importRun);

            $outcome = $this->service->bulkAssign(
                $importRun,
                $request->selectAll(),
                $request->rowIds(),
                $request->mode(),
                $request->operatorId(),
                $request->operationalSiteId(),
                $request->productIds(),
            );
            $this->service->recomputeCounts($importRun->fresh());

            return $this->ok(['updated' => $outcome->assigned, 'skipped' => $outcome->skipped]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * PATCH /api/imports/{domain}/{importRun}/rows/{row}/resolution — the
     * operator's per-row duplicate decision (spec 0036, AC-003): skip/create/
     * update, valid only for a `duplicate` row on a `reviewing` run. Read
     * back by ProcessStagedImportJob at commit time; no client-side write
     * logic here beyond validation.
     */
    public function updateRowResolution(ResolveImportRowRequest $request, string $domain, ImportRun $importRun, ImportRunRow $row): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->assertOwnedRun($importRun, $actor, $domain);
            $this->assertRowBelongsToRun($row, $importRun);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $actor);
            $this->assertReviewing($importRun);
            $this->assertRowIsDuplicate($row);

            $row->update(['resolution' => $request->validated('resolution')]);

            return $this->ok([
                'row' => new ImportRunRowResource($row->fresh()),
                'counts' => $this->summaryBuilder->counts($importRun),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id, 'row' => $row->id]);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun}/summary — pre-confirm recap
     * (spec 0033), valid only from `reviewing`.
     */
    public function summary(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('view', $importRun);
            $this->assertReadableStatus($importRun);

            return $this->ok(['summary' => $this->summaryBuilder->summary($importRun)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * POST /api/imports/{domain}/{importRun}/confirm — move a reviewing (or,
     * legacy, awaiting_confirmation) run to processing and dispatch the
     * commit job. Any other status → 422 (ImportService::confirm()/
     * confirmStaged()). Optional `convert_to_opportunity` (spec 0045):
     * additionally requires `opportunities.create` — checked here BEFORE the
     * service call, mirroring LeadController::store()'s own
     * `convert_to_opportunity` gate, so a forbidden request never persists
     * anything (never leave this to the frontend-only `<Can>` gate). When
     * true and the run is not ready to convert, ImportService::
     * confirmStaged() throws ImportConversionNotReadyException, caught here
     * BEFORE the generic Throwable handler to build the frozen 422
     * `convert_blockers` body (not expressible through the generic fail()
     * envelope helper).
     */
    public function confirm(ConfirmImportRequest $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('update', $importRun);
            $this->authorizeImport($definition, $request->user());

            $convertToOpportunity = (bool) $request->safe()->boolean('convert_to_opportunity', false);

            if ($convertToOpportunity) {
                $this->authorize('create', Opportunity::class);
            }

            $run = $this->isWizardDefinition($definition)
                ? $this->service->confirmStaged($importRun, $convertToOpportunity)
                : $this->service->confirm($importRun);

            return $this->ok(['import_run' => new ImportRunResource($run)]);
        } catch (ImportConversionNotReadyException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'convert_blockers' => $exception->blockers(),
            ], 422);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun}/errors — downloadable CSV of every
     * rejected row (not just the preview sample); 404 when no report exists.
     */
    public function errors(Request $request, string $domain, ImportRun $importRun): StreamedResponse|JsonResponse
    {
        try {
            $this->registry->resolve($domain); // 404 if unknown
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->authorize('view', $importRun);
            $this->assertHasErrorReport($importRun);

            return Storage::disk('local')->download(
                $importRun->error_report_path,
                "{$domain}-import-errors-{$importRun->id}.csv",
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }
}
