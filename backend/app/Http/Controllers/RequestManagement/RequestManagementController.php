<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Enums\FormMode;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\AssignRequestManagerGa1Request;
use App\Http\Requests\RequestManagement\AssignRequestOperatorsRequest;
use App\Http\Requests\RequestManagement\RequestFormContextRequest;
use App\Http\Requests\RequestManagement\StoreRequestRequest;
use App\Http\Requests\RequestManagement\TransferRequestsRequest;
use App\Http\Requests\RequestManagement\UpdateRequestRequest;
use App\Http\Resources\RequestFormContextResource;
use App\Http\Resources\RequestManagementResource;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestAttributeResolver;
use App\Services\QuoteService;
use App\Services\RequestManagement\RequestAssignmentService;
use App\Services\RequestManagement\RequestCreationService;
use App\Services\RequestManagement\RequestManagementScope;
use App\Services\RequestManagement\RequestManagementService;
use App\Services\RequestManagement\RequestTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Dedicated create/show/update endpoints for the "Gestione Richieste" work
 * panel (spec 0049/0057, migrated onto the Quote by spec 0086): a grid row
 * IS a Quote (D-1/D-2), but access runs through its OWN `request-management.*`
 * permissions and the D-3 supervisor-scoping guard, never `quotes.*`/PATCH
 * /api/quotes/{id}. `store()` has no scope guard to run (there is no
 * {quote} yet).
 *
 * Thin controller: permission gate + scope guard, FormRequest validation,
 * Service call, Resource output. Every action attaches the same
 * `permissions` metadata block (spec 0004) as OpportunityController.
 *
 * @see RequestManagementService
 * @see RequestCreationService
 * @see RequestManagementScope
 */
class RequestManagementController extends BaseApiController
{
    public function __construct(
        private readonly RequestManagementService $service,
        private readonly RequestManagementScope $scope,
        private readonly RequestAssignmentService $assignmentService,
        private readonly RequestTransferService $transferService,
        private readonly RequestCreationService $creationService,
        private readonly QuoteService $quoteService,
        private readonly RequestAttributeResolver $attributeResolver,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * POST /api/request-management/form-context (user directive 2026-08-07):
     * the "Informazioni aggiuntive" the create form must render for the
     * product-line categories picked so far — nothing is persisted yet, so
     * there is no record to resolve them from. Read-only despite the verb
     * (the criteria are a collection of objects), gated by the SAME
     * `request-management.create` that gates the form itself.
     */
    public function formContext(RequestFormContextRequest $request): JsonResponse
    {
        try {
            abort_unless($request->user()->can('request-management.create'), 403);

            return $this->ok(new RequestFormContextResource(
                $this->attributeResolver->forCategories($request->productCategoryIds(), FormMode::Create),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/request-management (spec 0057; D-5): creates the Opportunity
     * behind a new "Gestione Richieste" row, then the Offerta itself.
     */
    public function store(StoreRequestRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.create'), 403);

            $data = $request->toData();
            // Assigning the team up front is a supervisory act (user
            // directive 2026-07-29; spec 0097, D-1: the lone GA2 "Operatore"
            // is now one slot of `manager_slots`): creating a request never
            // implies deciding who works it. An all-empty payload asks for
            // nobody in particular — it resolves to the creating actor on the
            // OPERATOR slot (RequestCreationService), the same default an
            // absent key gets, so it stays open to every creator.
            abort_unless(
                $this->submittedManagerSlots($data) === [] || $user->can('request-management.assignOperator'),
                403,
            );
            // Same rule for the Sede operativa (user directive 2026-08-03),
            // gated by the ability its own field ceiling already hangs off
            // (RequestManagementAuthorization::fieldPermissionCeiling). The
            // field-permission matrix cannot cover creation — there is no
            // persisted model to resolve it against — so the guard lives here.
            abort_unless(
                $data->operationalSiteId === null || $user->can('operational-sites.viewAny'),
                403,
            );

            $panel = $this->creationService->create($user, $data);
            /** @var Quote $quote */
            $quote = $panel['quote'];

            return $this->okWithPermissions(
                new RequestManagementResource($panel),
                $this->buildPermissions($user, $quote),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The user ids the create payload actually asks for — `[]` when the key
     * was absent or every slot was left empty (spec 0097).
     *
     * @return array<int, int>
     */
    private function submittedManagerSlots(CreateRequestData $data): array
    {
        return array_values(array_filter(
            $data->managerSlots ?? [],
            static fn (?int $userId): bool => $userId !== null,
        ));
    }

    /**
     * GET /api/request-management/{quote} — the work panel.
     */
    public function show(Request $request, Quote $quote): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.view'), 403);
            $this->scope->assertInScope($user, $quote);

            return $this->okWithPermissions(
                new RequestManagementResource($this->service->loadWorkPanel($quote)),
                $this->buildPermissions($user, $quote),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * PUT/PATCH /api/request-management/{quote} — persist the operative
     * fields (sparse diff), routed onto the Quote or its Opportunity per
     * field (spec 0086, D-2).
     */
    public function update(UpdateRequestRequest $request, Quote $quote): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            $this->scope->assertInScope($user, $quote);

            $panel = $this->service->updateWork(
                $quote,
                $user,
                [
                    ...$request->safe()->only([
                        'next_callback_at',
                        'source_id',
                        'reporter_id',
                        // Spec 0097, D-9: the "Supervisore", the Offerta's
                        // commission recipient — an attribution scalar
                        // written like the two above, independent of the
                        // team keys below (AC-014).
                        'supervisor_id',
                        // Spec 0097, D-5: the panel writes the WHOLE team.
                        // `operator_id` is deliberately NOT in this list any
                        // more — UpdateRequestRequest no longer validates it,
                        // so it could never reach `validated()`; it survives
                        // as `updateWork()`'s internal key for the grid cell,
                        // the bulk assign and the transfer.
                        'manager_slots',
                        // Spec 0056: the Sede operativa, same attribution block.
                        'operational_site_id',
                        // Spec 0059, AC-023: same sparse rule — absent means
                        // untouched, `[]` clears every reward assignment.
                        'rewards',
                        // Funzione aziendale + categoria prodotto (user
                        // directive 2026-07-31): a full-replace collection,
                        // sparse like every other key here.
                        'product_lines',
                        // "Linee dell'offerta" (user directive 2026-08-07):
                        // the Offerta's own REVENUE rows, full-replace when
                        // submitted and untouched when absent — the same
                        // convention the quotes PATCH follows.
                        'offer_lines',
                        // User directive 2026-08-07: the Offerta's own
                        // "Informazioni aggiuntive" and "Stato di
                        // lavorazione". `note` travels with the status: it is
                        // what a `requires_note` destination demands.
                        'attribute_values',
                        'quote_workflow_status_id',
                        'note',
                    ]),
                    // Typed DTOs (ContactInput/AddressInput), not raw arrays:
                    // the client anagraphic block never reaches the service as
                    // request input.
                    ...$request->clientProfilePayload(),
                ],
            );

            return $this->okWithPermissions(
                new RequestManagementResource($panel),
                $this->buildPermissions($user, $quote),
                'Updated',
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * DELETE /api/request-management/{quote} — the row action behind the
     * table's "Elimina" (user directive 2026-07-23). Gated by this module's
     * OWN `request-management.delete` plus the D-3 scope, never
     * `quotes.delete`; the record removed IS the Offerta (spec 0086, AC-031):
     * deleted through QuoteService::delete() so the Opportunity survives.
     */
    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.delete'), 403);
            $this->scope->assertInScope($user, $quote);

            $this->quoteService->delete($quote);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['quote' => $quote->id]);
        }
    }

    /**
     * POST /api/request-management/assign-operators — bulk-assign a Sede
     * operativa and the GA2 "Operatore" to many requests at once (user
     * directive 2026-07-23, "come nei lead"). The per-row D-3 scope is
     * enforced inside the service, which SKIPS every unreachable id rather
     * than failing the batch — an out-of-scope row does not exist for this
     * actor, so `assigned` reports what was actually written. The ids are
     * Offerta ids (spec 0086).
     *
     * `assignOperator` on top of `update` (user directive 2026-08-03): this
     * endpoint writes the Sede AND the Operatore of many requests at once, the
     * two dimensions a role may be restricted on per-field — `update` alone
     * would have been a way around that restriction, since a bulk write
     * resolves no field permission.
     */
    public function assignOperators(AssignRequestOperatorsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            abort_unless($user->can('request-management.assignOperator'), 403);

            $assigned = $this->assignmentService->assignOperators(
                $request->requestIds(),
                $user,
                $request->operationalSiteId(),
                $request->mode(),
                $request->operatorId(),
            );

            return $this->ok(['assigned' => $assigned], 'Operators assigned');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/request-management/assign-manager-ga1 (spec 0104, direttiva
     * utente 2026-09-07) — moves the GA1 slot of one or many Offerte onto a
     * single chosen user, or CLEARS it when `manager_ga1_id` is null. No
     * Sede, no mode: only the GA2 Operatore slot is bound to a Sede, so this
     * action has neither a site to pick nor a site-scoped pool to balance
     * across (D-1).
     *
     * Same D-3 skip-in-scope semantics as assignOperators() above: an id the
     * actor may not reach is silently excluded, never a 403/404 on the batch
     * (`assigned` reports what was actually reached).
     *
     * `assignManagerGa1` on top of `update`, mirroring `assignOperator`: a
     * bulk write resolves no per-field permission, so restricting the
     * `manager_ga1_id` field alone would leave this endpoint as the way
     * around that restriction.
     */
    public function assignManagerGa1(AssignRequestManagerGa1Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            abort_unless($user->can('request-management.assignManagerGa1'), 403);

            $assigned = $this->assignmentService->assignManagerGa1(
                $request->requestIds(),
                $user,
                $request->managerGa1Id(),
            );

            return $this->ok(['assigned' => $assigned], 'Manager GA1 assigned');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/request-management/transfer (spec 0079) — transfers one or
     * many requests to another Sede operativa, assigning that site's own GA2
     * "Operatore" in the same call. Same D-3 skip-in-scope semantics as
     * assignOperators() above: an id the actor may not reach is silently
     * excluded, never a 403/404 on the batch (`transferred` reports what was
     * actually written). The ids are Offerta ids (spec 0086).
     *
     * `transferContact` on top of `update`, mirroring `assignOperator` above:
     * the endpoint writes the Sede AND the Operatore of many requests at
     * once, the two per-field-restricted dimensions, so `update` alone would
     * be a way around that restriction.
     */
    public function transfer(TransferRequestsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            abort_unless($user->can('request-management.transferContact'), 403);

            $transferred = $this->transferService->transfer(
                $request->requestIds(),
                $user,
                $request->operationalSiteId(),
                $request->operatorId(),
            );

            return $this->ok(['transferred' => $transferred], 'Contacts transferred');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $quote, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Quote $quote): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('request-management'), $actor, $quote);
    }
}
