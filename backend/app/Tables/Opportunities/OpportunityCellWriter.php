<?php

declare(strict_types=1);

namespace App\Tables\Opportunities;

use App\Authorization\AuthorizationRegistry;
use App\Http\Requests\Opportunities\UpdateOpportunityRequest;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\Table\FormRequestCellValidator;
use App\Support\ManagerPositions;

/**
 * The write behind the opportunities grid's inline cell edit (spec 0206,
 * D-2): the cell's field goes through UpdateOpportunityRequest — rules, the
 * lead lock on anagrafica/fonte, rewards, field permissions — and then
 * OpportunityService::update(), so the title writer, the product-line
 * coverage rule, the manager propagation and the assignment notifications run
 * exactly as from the detail.
 */
final class OpportunityCellWriter
{
    private const string ROUTE_PARAMETER = 'opportunity';

    private const string RESOURCE = 'opportunities';

    private const string MANAGER_SLOTS_FIELD = 'manager_slots';

    private const string REGISTRY_FIELD = 'registry_id';

    /** The single-id roles an anagrafica hands down to its opportunity (spec 0040 A-5). */
    private const array INHERITED_ROLE_FIELDS = ['commercial_id', 'reporter_id', 'supervisor_id'];

    public function __construct(
        private readonly FormRequestCellValidator $formRequest,
        private readonly OpportunityService $service,
        private readonly AuthorizationRegistry $authorization,
    ) {}

    public function write(Opportunity $opportunity, string $fieldKey, mixed $value, User $actor): Opportunity
    {
        // Step 1: the payload the form itself would send for this one change.
        $payload = match ($fieldKey) {
            // D-3: the cell holds a list of people, the form a positional team.
            self::MANAGER_SLOTS_FIELD => [$fieldKey => ManagerPositions::slotsFor($opportunity, $value)],
            self::REGISTRY_FIELD => $this->registryPayload($opportunity, $value, $actor),
            default => [$fieldKey => $value],
        };

        // Step 2: the form's own rules, then its own service.
        $request = $this->formRequest->validate(UpdateOpportunityRequest::class, self::ROUTE_PARAMETER, $opportunity, $actor, $payload);

        return $this->service->update($opportunity, $request->toData(), $actor);
    }

    /**
     * D-4 (user decision): a different anagrafica clears the referent (it
     * belongs to the previous one, BR-4) and fills every EMPTY role the actor
     * may edit with the new anagrafica's own — a role already set is never
     * overwritten from the grid, which has no confirmation step.
     *
     * @return array<string, mixed>
     */
    private function registryPayload(Opportunity $opportunity, mixed $registryId, User $actor): array
    {
        $payload = [self::REGISTRY_FIELD => $registryId];

        if ($registryId === $opportunity->registry_id) {
            return $payload;
        }

        $payload['referent_id'] = null;
        $registry = $registryId === null ? null : Registry::query()->with('managers')->find($registryId);

        if ($registry === null) {
            return $payload;
        }

        $editable = $this->editableFields($opportunity, $actor);

        foreach (self::INHERITED_ROLE_FIELDS as $field) {
            if ($opportunity->{$field} === null && $registry->{$field} !== null && in_array($field, $editable, true)) {
                $payload[$field] = $registry->{$field};
            }
        }

        $opportunity->loadMissing('managers');

        if ($opportunity->managers->isEmpty() && $registry->managers->isNotEmpty() && in_array(self::MANAGER_SLOTS_FIELD, $editable, true)) {
            // The anagrafica's own positional team, gaps included.
            $payload[self::MANAGER_SLOTS_FIELD] = ManagerPositions::slotsFor($registry, $registry->managers->modelKeys());
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function editableFields(Opportunity $opportunity, User $actor): array
    {
        $permissions = $this->authorization->resolve(self::RESOURCE)->fieldPermissions($actor, $opportunity);

        return array_keys(array_filter($permissions, static fn ($permission): bool => $permission->editable));
    }
}
