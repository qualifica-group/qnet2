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
use App\Services\Assignment\AssignmentCandidates;
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
 * `single_operator_available` answers what the union of the categories
 * structurally cannot: that union is an OR — an operator competent for at
 * least ONE of the selected records' categories passes the picker's filter —
 * while `mode=single` demands an AND, and on Gestione richieste rejects with
 * a 422 an operator who does not cover EVERY targeted offer (rev.3). The
 * field is the INTERSECTION of the per-record candidate pools being
 * non-empty, so the client can tell a selection one operator can take from
 * one it cannot before proposing the choice.
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
        private readonly AssignmentCandidates $candidates,
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

        // Step 4: fold them onto the four answers.
        $campaignIds = $rows
            ->map(static fn (ImportRunRow $row): ?int => LeadRowCampaign::resolve($row->mapped_values ?? [], $globalConfig))
            ->all();

        $rowIds = $rows->pluck('id')->map(intval(...))->all();
        $siteByRow = $this->siteResolver->forImportRows($rows, $globalConfig);

        return [
            'product_category_ids' => $this->importRowCompetence->requiredUnion($rows, $globalConfig),
            'operational_site_id' => $this->sharedSite($rowIds, $siteByRow),
            'campaign_ids' => $this->ascendingIds($campaignIds),
            'single_operator_available' => $this->singleOperatorAvailable(
                $rowIds,
                $siteByRow,
                $this->importRowCompetence->requiredByRow($rows, $globalConfig),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromLeads(SelectionScopeRequest $request): array
    {
        $this->authorize('viewAny', Lead::class);

        $leadIds = $request->ids();
        $siteByLead = $this->siteResolver->forLeads($leadIds);

        return [
            'product_category_ids' => $this->leadCompetence->requiredUnion($leadIds),
            'operational_site_id' => $this->sharedSite($leadIds, $siteByLead),
            'campaign_ids' => $this->ascendingIds(
                Lead::query()->whereIn('id', $leadIds)->pluck('campaign_id')->all(),
            ),
            'single_operator_available' => $this->singleOperatorAvailable(
                $leadIds,
                $siteByLead,
                $this->leadCompetence->requiredByLead($leadIds),
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

        $siteByQuote = $this->siteResolver->forQuotes($inScopeIds);

        return [
            'product_category_ids' => $this->quoteCompetence->requiredUnion($inScopeIds),
            'operational_site_id' => $this->sharedSite($inScopeIds, $siteByQuote),
            // An Opportunity carries no campaign (D-4): the chain does not
            // exist on this domain.
            'campaign_ids' => [],
            'single_operator_available' => $this->singleOperatorAvailable(
                $inScopeIds,
                $siteByQuote,
                $this->quoteCompetence->requiredByQuote($inScopeIds),
            ),
        ];
    }

    /**
     * Whether ONE operator can take the WHOLE selection: the INTERSECTION of
     * the per-record candidate pools is not empty. The pools come from
     * AssignmentCandidates, the same composition the three assignment
     * surfaces apply when they actually assign, so the picker cannot answer
     * a rule the assignment does not enforce.
     *
     * The edges are conventions, and each is deliberate:
     *   - an EMPTY selection — including one whose records are all outside
     *     the actor's D-3 scope — answers true: there is nothing to cover,
     *     and an out-of-scope offer must not disable a UI it does not exist
     *     in;
     *   - a record with no resolvable Sede has an empty pool, so it alone
     *     answers false (AC-007);
     *   - a record demanding no category keeps its whole Sede rather than
     *     everybody (AC-008), so it narrows the intersection without
     *     constraining the competence.
     *
     * Answered for the three domains, not only for the one whose `single`
     * mode rejects today: a field present on one domain only would make the
     * contract unpredictable. Which surface acts on it is the client's call.
     *
     * @param  array<int, int>  $recordIds
     * @param  array<int, int|null>  $siteByRecord
     * @param  array<int, array<int, int>>  $categoriesByRecord
     */
    private function singleOperatorAvailable(array $recordIds, array $siteByRecord, array $categoriesByRecord): bool
    {
        if ($recordIds === []) {
            return true;
        }

        $candidatesByRecord = $this->candidates->byRecord($siteByRecord, $categoriesByRecord);

        $shared = null;

        foreach ($recordIds as $recordId) {
            $pool = $candidatesByRecord[$recordId] ?? [];

            $shared = $shared === null ? $pool : array_intersect($shared, $pool);

            if ($shared === []) {
                return false;
            }
        }

        return true;
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
