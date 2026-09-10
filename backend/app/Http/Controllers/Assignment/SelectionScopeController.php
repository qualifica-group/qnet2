<?php

namespace App\Http\Controllers\Assignment;

use App\Enums\AssignmentDomain;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Controllers\Import\Concerns\GuardsImportRequests;
use App\Http\Requests\Assignment\SelectionScopeRequest;
use App\Imports\ImportRegistry;
use App\Imports\Leads\LeadRowCampaign;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\User;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\ImportRowCompetence;
use App\Services\Assignment\ImportRunRowSelection;
use App\Services\Assignment\LeadCompetence;
use App\Services\Assignment\QuoteCompetence;
use App\Services\RequestManagement\RequestManagementScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/assignment/selection-scope (spec 0113, renamed from
 * `required-categories` of spec 0110) — everything a SELECTION of records
 * demands of its operator: the union of its records' own competence
 * requirements (D-14), the Sede that scopes them and the campaigns they span.
 * One endpoint for the three assignment surfaces: the operator picker asks it
 * once, then hands the answer to
 * `GET /api/users/for-select?operational_site_id=...&competence_category_ids[]=...`.
 * Empty categories mean "no requirement": the caller applies no filter.
 *
 * The Sede is the SHARED one, computed over the resolved value of EVERY
 * targeted record, null included as a value: one distinct non-null value wins,
 * anything else — several Sedi, or a single record whose Sede is unresolvable —
 * answers null, because a picker narrowed to a Sede half the selection does not
 * belong to would propose operators nobody can be assigned to. The client only
 * filters with it: the server always recomputes the Sede from the record when
 * it actually assigns (constraints).
 *
 * Read-only, and gated by the READ permission of the requested domain, never
 * by a gate of its own (constraints): `leads.import` + run ownership for the
 * staged rows, `leads.viewAny` for the real leads, `request-management.viewAny`
 * for the offers — plus, for the offers, the module's own D-3 row scope, so
 * an offer the actor may not reach contributes neither its categories nor its
 * Sede to the answer (AC-025).
 */
class SelectionScopeController extends BaseApiController
{
    use AuthorizesRequests;
    use GuardsImportRequests;

    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportRunRowSelection $rowSelection,
        private readonly ImportRowCompetence $importRowCompetence,
        private readonly LeadCompetence $leadCompetence,
        private readonly QuoteCompetence $quoteCompetence,
        private readonly AssignmentSiteResolver $siteResolver,
    ) {}

    public function __invoke(SelectionScopeRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $scope = match ($request->domain()) {
                AssignmentDomain::ImportRows => $this->fromImportRows($request, $actor),
                AssignmentDomain::Leads => $this->fromLeads($request),
                AssignmentDomain::Quotes => $this->fromQuotes($request, $actor),
            };

            return $this->ok($scope);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fromImportRows(SelectionScopeRequest $request, User $actor): array
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

        // Step 3: read the targeted rows ONCE. The projection is exactly what
        // ImportRowCompetence and LeadRowCampaign read (R-3: a big run must
        // not be hydrated to answer a picker).
        $globalConfig = $run->global_config ?? [];
        $rows = $this->rowSelection->rows(
            $run,
            $request->selectAll(),
            $request->rowIds(),
            ['id', 'product_ids', 'mapped_values'],
        );

        // Step 4: fold them onto the three answers.
        $campaignIds = $rows
            ->map(static fn (ImportRunRow $row): ?int => LeadRowCampaign::resolve($row->mapped_values ?? [], $globalConfig))
            ->all();

        return [
            'product_category_ids' => $this->importRowCompetence->requiredUnion($rows, $globalConfig),
            'operational_site_id' => $this->sharedSite(
                $rows->pluck('id')->map(intval(...))->all(),
                $this->siteResolver->forImportRows($rows, $globalConfig),
            ),
            'campaign_ids' => $this->ascendingIds($campaignIds),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromLeads(SelectionScopeRequest $request): array
    {
        $this->authorize('viewAny', Lead::class);

        $leadIds = $request->ids();

        return [
            'product_category_ids' => $this->leadCompetence->requiredUnion($leadIds),
            'operational_site_id' => $this->sharedSite($leadIds, $this->siteResolver->forLeads($leadIds)),
            'campaign_ids' => $this->ascendingIds(
                Lead::query()->whereIn('id', $leadIds)->pluck('campaign_id')->all(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromQuotes(SelectionScopeRequest $request, User $actor): array
    {
        abort_unless($actor->can('request-management.viewAny'), 403);

        // D-3, restated as a read rule: an offer outside the actor's scope
        // does not exist for them, so it must not leak what it demands nor
        // where it sits.
        $inScopeIds = RequestManagementScope::scopeToActor(
            Quote::query()->whereIn('quotes.id', $request->ids()),
            $actor,
        )->pluck('quotes.id')->map(intval(...))->all();

        return [
            'product_category_ids' => $this->quoteCompetence->requiredUnion($inScopeIds),
            'operational_site_id' => $this->sharedSite($inScopeIds, $this->siteResolver->forQuotes($inScopeIds)),
            // An Opportunity carries no campaign (D-4): the chain does not
            // exist on this domain.
            'campaign_ids' => [],
        ];
    }

    /**
     * The Sede SHARED by the selection: the single distinct resolved value
     * when there is exactly one and it is not null, null otherwise. A record
     * missing from the map counts as a null value, so one unresolvable record
     * is enough to leave the picker unfiltered rather than filtered on a Sede
     * that does not hold for the whole selection.
     *
     * @param  array<int, int>  $recordIds
     * @param  array<int, int|null>  $siteByRecord
     */
    private function sharedSite(array $recordIds, array $siteByRecord): ?int
    {
        $sites = [];

        foreach ($recordIds as $recordId) {
            $site = $siteByRecord[$recordId] ?? null;

            if (! in_array($site, $sites, true)) {
                $sites[] = $site;
            }

            if (count($sites) > 1) {
                return null;
            }
        }

        return $sites[0] ?? null;
    }

    /**
     * @param  array<int, int|null>  $ids
     * @return array<int, int>
     */
    private function ascendingIds(array $ids): array
    {
        $ids = array_map(intval(...), array_filter($ids, static fn (mixed $id): bool => $id !== null));
        $ids = array_values(array_unique(array_filter($ids)));

        sort($ids);

        return $ids;
    }
}
