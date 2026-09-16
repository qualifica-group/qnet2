<?php

namespace App\Http\Controllers\Import;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Import\StoreImportMappingTemplateRequest;
use App\Http\Resources\ImportMappingTemplateResource;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Imports\Support\ColumnAnalysis;
use App\Models\ImportMappingTemplate;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Team-shared, per-domain saved column-mapping templates (spec 0035): one
 * controller serves every import domain, {domain} resolves through the
 * registry (unknown → 404), mirroring ImportController.
 *
 * List/create enforce the SAME gate as ImportController::template() (the CSV
 * template download) — the domain's `{resource}.import` ability (`leads.import`
 * for the only registered domain), resolved both via `$this->authorize()`
 * against ImportRun (ImportRunPolicy → `leads.import`) and `authorizeImportGate()`
 * (the definition's own `{resource}.import`). Delete is owner-only via
 * ImportMappingTemplatePolicy (no gate duplication: Gate::before covers the
 * super-admin bypass). A bound {mappingTemplate} whose `resource` does not
 * match {domain} 404s, mirroring TableFilterViewController::
 * assertBelongsToDomain.
 */
class ImportMappingTemplateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly ImportRegistry $registry) {}

    /**
     * GET /api/imports/{domain}/mapping-templates — every saved template for
     * the domain (team-shared), most recent first.
     */
    public function index(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorize('create', ImportRun::class);
            $this->authorizeImportGate($definition, $actor);

            $templates = ImportMappingTemplate::query()
                ->where('resource', $domain)
                ->with('user')
                ->latest('id')
                ->get();

            return $this->ok(['mapping_templates' => ImportMappingTemplateResource::collection($templates)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/imports/{domain}/mapping-templates — snapshot the resolved
     * run's mapping/dedup strategy as a new named, team-shared template.
     */
    public function store(StoreImportMappingTemplateRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorize('create', ImportRun::class);
            $this->authorizeImportGate($definition, $actor);

            $run = $this->resolveRun((int) $request->validated('import_run_id'), $domain);
            $this->assertRunHasMapping($run);

            $template = ImportMappingTemplate::create([
                'resource' => $domain,
                'user_id' => $actor->id,
                'name' => $request->validated('name'),
                'columns' => ColumnAnalysis::columnKeys($run->detected_columns ?? []),
                'column_mapping' => $run->column_mapping,
                'dedup_strategy' => $run->dedup_strategy,
            ]);

            return $this->created(['mapping_template' => new ImportMappingTemplateResource($template->load('user'))]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/imports/{domain}/mapping-templates/{mappingTemplate} —
     * delete an owned template.
     */
    public function destroy(Request $request, string $domain, ImportMappingTemplate $mappingTemplate): JsonResponse
    {
        try {
            $this->registry->resolve($domain); // 404 if unknown
            $this->assertBelongsToDomain($mappingTemplate, $domain);
            $this->authorize('delete', $mappingTemplate);

            $mappingTemplate->delete();

            return $this->ok();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['mappingTemplate' => $mappingTemplate->id]);
        }
    }

    /**
     * A run whose resource does not match the route {domain} must never leak
     * cross-domain: surfaced as 404 (not 403), identical to an unknown id —
     * same semantics as ImportController::assertRunMatchesDomain(). Runs are
     * not owner-scoped (user decision 2026-09-16).
     *
     * @throws ModelNotFoundException
     */
    private function resolveRun(int $importRunId, string $domain): ImportRun
    {
        $run = ImportRun::find($importRunId);

        if ($run === null || $run->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRunId]);
        }

        return $run;
    }

    /**
     * A run still `analyzing`/`configuring` (never reached configure()) has
     * nothing to snapshot yet.
     */
    private function assertRunHasMapping(ImportRun $run): void
    {
        if ($run->column_mapping === null) {
            abort(422, 'The import run has no persisted column mapping yet.');
        }
    }

    /**
     * A bound {mappingTemplate} whose domain does not match the route
     * {domain} must never leak cross-domain: surfaced as 404 (not 403),
     * identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertBelongsToDomain(ImportMappingTemplate $mappingTemplate, string $domain): void
    {
        if ($mappingTemplate->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ImportMappingTemplate::class, [$mappingTemplate->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403. Same
     * pattern as ImportController::authorizeImport().
     *
     * @throws AuthorizationException
     */
    private function authorizeImportGate(ImportDefinition $definition, User $actor): void
    {
        if (! $definition->authorizeImport($actor)) {
            throw new AuthorizationException;
        }
    }
}
