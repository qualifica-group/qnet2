<?php

namespace App\Http\Controllers\Leads;

use App\Actions\Leads\ConvertLeadsToOpportunities;
use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Exceptions\Leads\BulkConversionBlockedException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Leads\AssignOperatorsRequest;
use App\Http\Requests\Leads\BulkConvertLeadsRequest;
use App\Http\Requests\Leads\StoreLeadRequest;
use App\Http\Requests\Leads\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\LeadAssignmentService;
use App\Services\LeadService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `leads` resource (spec 0024), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (LeadPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned lead.
 *
 * @see LeadService
 */
class LeadController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LeadService $service,
        private readonly LeadAssignmentService $assignmentService,
        private readonly ConvertLeadsToOpportunities $bulkConverter,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/leads/{lead} — single lead (view row-action).
     */
    public function show(Request $request, Lead $lead): JsonResponse
    {
        try {
            $this->authorize('view', $lead);

            return $this->okWithPermissions(
                new LeadResource($this->service->loadDetail($lead)),
                $this->buildPermissions($request->user(), $lead),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['lead' => $lead->id]);
        }
    }

    /**
     * POST /api/leads — create a new lead. `convert_to_opportunity` (spec
     * 0044) additionally requires `opportunities.create`, checked here
     * BEFORE the service call so a forbidden request never persists a Lead.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Lead::class);

            $data = $request->toData();

            if ($data->convertToOpportunity) {
                $this->authorize('create', Opportunity::class);
            }

            $lead = $this->service->create($data, $request->user());

            return $this->okWithPermissions(
                new LeadResource($lead),
                $this->buildPermissions($request->user(), $lead),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/leads/{lead} — update an existing lead.
     */
    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        try {
            $this->authorize('update', $lead);

            $lead = $this->service->update($lead, $request->toData());

            return $this->okWithPermissions(
                new LeadResource($lead),
                $this->buildPermissions($request->user(), $lead),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['lead' => $lead->id]);
        }
    }

    /**
     * POST /api/leads/assign-operators — bulk-assign an Operatore to many
     * REAL leads at once (spec 0048), each scoped by the Sede of its own
     * campaign (spec 0113, D-3). Every targeted lead is
     * authorized individually via LeadPolicy (`leads.update` — the policy
     * carries no per-record ownership rule, so this is equivalent to a
     * single blanket check, but stays instance-based to match every other
     * Lead endpoint and to remain correct should the policy ever gain one).
     *
     * `skipped` (spec 0110, additive): the leads `mode=balanced` left without
     * an operator for lack of a competent one at their own Sede. Always 0
     * with `mode=single`.
     */
    public function assignOperators(AssignOperatorsRequest $request): JsonResponse
    {
        try {
            $leadIds = $request->leadIds();

            foreach (Lead::query()->whereIn('id', $leadIds)->get() as $lead) {
                $this->authorize('update', $lead);
            }

            $outcome = $this->assignmentService->assignOperators(
                $leadIds,
                $request->mode(),
                $request->operatorId(),
            );

            return $this->ok(['assigned' => $outcome->assigned, 'skipped' => $outcome->skipped], 'Operators assigned');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/leads/convert-to-opportunities — convert many existing leads
     * into their derived Opportunity in one call (spec 0071, the table's mass
     * action). Unlike the single row action, which opens the prefilled
     * Opportunity form, this path derives everything server-side: the exact
     * same ConvertLeadToOpportunity the creation checkbox uses.
     *
     * Double-gated like the single conversion: OpportunityPolicy::create
     * (the ability that actually creates the records) plus LeadPolicy::view
     * per targeted lead, mirroring LeadOpportunityDefaultsController.
     *
     * All-or-nothing (D-1): a batch holding a non-convertible lead answers 422
     * with the full blocker list and writes nothing — not expressible through
     * the generic exception handler, hence the dedicated catch.
     */
    public function convertToOpportunities(BulkConvertLeadsRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Opportunity::class);

            $leadIds = $request->leadIds();

            foreach (Lead::query()->whereIn('id', $leadIds)->get() as $lead) {
                $this->authorize('view', $lead);
            }

            $opportunityIds = $this->bulkConverter->handle($leadIds, $request->user());

            return $this->ok([
                'converted' => count($opportunityIds),
                'opportunity_ids' => $opportunityIds,
            ], 'Leads converted');
        } catch (BulkConversionBlockedException $blocked) {
            return $this->fail(
                $blocked->getMessage(),
                HttpStatusEnum::UNPROCESSABLE_ENTITY->value,
                ['reason' => BulkConversionBlockedException::REASON, 'blockers' => $blocked->blockers],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/leads/{lead} — delete a lead.
     */
    public function destroy(Lead $lead): JsonResponse
    {
        try {
            $this->authorize('delete', $lead);

            $this->service->delete($lead);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['lead' => $lead->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Lead $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('leads'), $actor, $model);
    }
}
