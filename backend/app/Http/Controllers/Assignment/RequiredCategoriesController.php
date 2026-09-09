<?php

namespace App\Http\Controllers\Assignment;

use App\Enums\AssignmentDomain;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\Import\Concerns\GuardsImportRequests;
use App\Http\Requests\Assignment\RequiredCategoriesRequest;
use App\Imports\ImportRegistry;
use App\Models\ImportRun;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\ImportRowCompetence;
use App\Services\Assignment\ImportRunRowSelection;
use App\Services\Assignment\LeadCompetence;
use App\Services\Assignment\QuoteCompetence;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/assignment/required-categories (spec 0110) — the product
 * categories a SELECTION of records demands of its operator, as the union of
 * its records' own requirements (D-14). One endpoint for the three
 * assignment surfaces: the operator picker asks it once, then hands the
 * answer to `GET /api/users/for-select?competence_category_ids[]=...`.
 * An empty answer means "no requirement": the caller applies no filter.
 *
 * Read-only, and gated by the READ permission of the requested domain, never
 * by a gate of its own (constraints): `leads.import` + run ownership for the
 * staged rows, `leads.viewAny` for the real leads, `request-management.viewAny`
 * for the offers — plus, for the offers, the module's own D-3 row scope, so
 * an offer the actor may not reach cannot contribute its categories to the
 * answer (AC-033).
 */
class RequiredCategoriesController extends BaseApiController
{
    use AuthorizesRequests;
    use GuardsImportRequests;

    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportRunRowSelection $rowSelection,
        private readonly ImportRowCompetence $importRowCompetence,
        private readonly LeadCompetence $leadCompetence,
        private readonly QuoteCompetence $quoteCompetence,
    ) {}

    public function __invoke(RequiredCategoriesRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $productCategoryIds = match ($request->domain()) {
                AssignmentDomain::ImportRows => $this->fromImportRows($request, $actor),
                AssignmentDomain::Leads => $this->fromLeads($request),
                AssignmentDomain::Quotes => $this->fromQuotes($request, $actor),
            };

            return $this->ok(['product_category_ids' => $productCategoryIds]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<int, int>
     */
    private function fromImportRows(RequiredCategoriesRequest $request, User $actor): array
    {
        // Step 1: resolve the run — an unknown id and another actor's run
        // answer the SAME 404 ImportController answers, never a 403.
        $run = ImportRun::query()->findOrFail($request->importRunId());
        $definition = $this->registry->resolve($run->resource); // 404 if unknown
        // This endpoint carries no {domain} segment: the run's own `resource`
        // IS the domain in play, so the guard reduces to its ownership half.
        $this->assertOwnedRun($run, $actor, $run->resource);

        // Step 2: the run's own read gates, unchanged (`leads.import`).
        $this->authorize('view', $run);
        $this->authorizeImport($definition, $actor);

        // Step 3: fold the targeted rows onto one requirement. The projection
        // is exactly what ImportRowCompetence reads (R-3: a big run must not
        // be hydrated to answer a picker).
        $rows = $this->rowSelection->rows(
            $run,
            $request->selectAll(),
            $request->rowIds(),
            ['id', 'product_ids', 'mapped_values'],
        );

        return $this->importRowCompetence->requiredUnion($rows, $run->global_config ?? []);
    }

    /**
     * @return array<int, int>
     */
    private function fromLeads(RequiredCategoriesRequest $request): array
    {
        $this->authorize('viewAny', Lead::class);

        return $this->leadCompetence->requiredUnion($request->ids());
    }

    /**
     * @return array<int, int>
     */
    private function fromQuotes(RequiredCategoriesRequest $request, User $actor): array
    {
        abort_unless($actor->can('request-management.viewAny'), 403);

        // D-3, restated as a read rule: an offer outside the actor's scope
        // does not exist for them, so it must not leak what it demands.
        $inScopeIds = RequestManagementScope::scopeToActor(
            Quote::query()->whereIn('quotes.id', $request->ids()),
            $actor,
        )->pluck('quotes.id')->map(intval(...))->all();

        return $this->quoteCompetence->requiredUnion($inScopeIds);
    }
}
