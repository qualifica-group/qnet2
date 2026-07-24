<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\AssignRequestOperatorsRequest;
use App\Http\Requests\RequestManagement\StoreRequestRequest;
use App\Http\Requests\RequestManagement\UpdateRequestRequest;
use App\Http\Resources\RequestManagementResource;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\RequestManagement\RequestAssignmentService;
use App\Services\RequestManagement\RequestCreationService;
use App\Services\RequestManagement\RequestManagementScope;
use App\Services\RequestManagement\RequestManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Dedicated create/show/update endpoints for the "Gestione Richieste" work
 * panel (spec 0049/0057): the record IS an Opportunity (D-1), but access runs
 * through its OWN `request-management.*` permissions and D-3 scoping guard,
 * never `opportunities.*`/PATCH /api/opportunities/{id}. `store()` has no
 * scope guard to run (there is no {opportunity} yet).
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
        private readonly RequestCreationService $creationService,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * POST /api/request-management (spec 0057): creates the Opportunity
     * behind a new "Gestione Richieste" row.
     */
    public function store(StoreRequestRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.create'), 403);

            $panel = $this->creationService->create($user, $request->toData());
            /** @var Opportunity $opportunity */
            $opportunity = $panel['opportunity'];

            return $this->okWithPermissions(
                new RequestManagementResource($panel),
                $this->buildPermissions($user, $opportunity),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/request-management/{opportunity} — the work panel.
     */
    public function show(Request $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.view'), 403);
            $this->scope->assertInScope($user, $opportunity);

            return $this->okWithPermissions(
                new RequestManagementResource($this->service->loadWorkPanel($opportunity)),
                $this->buildPermissions($user, $opportunity),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * PUT/PATCH /api/request-management/{opportunity} — advance the working
     * state and/or persist dynamic field values (sparse, D-4/D-5).
     */
    public function update(UpdateRequestRequest $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);
            $this->scope->assertInScope($user, $opportunity);

            $panel = $this->service->updateWork(
                $opportunity,
                $user,
                [
                    ...$request->safe()->only([
                        'opportunity_workflow_status_id',
                        // Spec 0054 D-5: the note that makes the advance to a
                        // `requires_note` status legal — dropped here means
                        // updateWork() sees none and rejects every such advance.
                        'note',
                        'attribute_values',
                        'next_callback_at',
                        'products_of_interest',
                        'source_id',
                        'reporter_id',
                        'operator_id',
                        // Spec 0056: the Sede operativa, same attribution block.
                        'operational_site_id',
                        // Spec 0059, AC-023: same sparse rule — absent means
                        // untouched, `[]` clears every reward assignment.
                        'rewards',
                    ]),
                    // Typed DTOs (ContactInput/AddressInput), not raw arrays:
                    // the client anagraphic block never reaches the service as
                    // request input.
                    ...$request->clientProfilePayload(),
                ],
            );

            return $this->okWithPermissions(
                new RequestManagementResource($panel),
                $this->buildPermissions($user, $opportunity),
                'Updated',
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * DELETE /api/request-management/{opportunity} — the row action behind
     * the table's "Elimina" (user directive 2026-07-23). Gated by this
     * module's OWN `request-management.delete` plus the D-3 scope, never
     * `opportunities.delete`; the record removed IS the Opportunity (D-1),
     * there is no separate request row.
     */
    public function destroy(Request $request, Opportunity $opportunity): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.delete'), 403);
            $this->scope->assertInScope($user, $opportunity);

            $opportunity->delete();

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['opportunity' => $opportunity->id]);
        }
    }

    /**
     * POST /api/request-management/assign-operators — bulk-assign a Sede
     * operativa and the GA2 "Operatore" to many requests at once (user
     * directive 2026-07-23, "come nei lead"). The per-row D-3 scope is
     * enforced inside the service, which SKIPS every unreachable id rather
     * than failing the batch — an out-of-scope row does not exist for this
     * actor, so `assigned` reports what was actually written.
     */
    public function assignOperators(AssignRequestOperatorsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            abort_unless($user->can('request-management.update'), 403);

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
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Opportunity $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('request-management'), $actor, $model);
    }
}
