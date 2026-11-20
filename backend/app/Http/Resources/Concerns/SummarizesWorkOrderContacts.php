<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\Referent;
use App\Models\Registry;
use App\Support\ReferentRef;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Http\Request;

/**
 * The client and the Referente of a single-offer commessa (WorkOrderResource),
 * each with its PRIMARY contacts so the operator can call the client from the
 * commessa itself (task 428287).
 *
 * The Referente is the offer's own (`quote.opportunity.referent`) and, when the
 * offer never named one, the anagrafica's primary referent: almost every
 * migrated offer has no referent of its own, so the row was empty on nearly
 * every commessa. `is_registry_primary` tells the reader which of the two it is.
 *
 * Contact values are personal data: they travel only when the actor may view
 * that registry / referent (the LeadResource boundary); otherwise the key is
 * there, empty. Relies on WorkOrderService::DETAIL_RELATIONS eager-loading the
 * `personalData.contacts` of both, so this never queries.
 */
trait SummarizesWorkOrderContacts
{
    /**
     * The client of the commessa, reached through `quote.opportunity` (a work
     * order has no `registry_id` of its own). Spec 0173: the detail mounts its
     * documents read-only.
     *
     * @return array{id: int, name: string, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>}|null
     */
    private function summarizeRegistry(Request $request): ?array
    {
        /** @var Registry|null $registry */
        $registry = $this->quote?->opportunity?->registry;

        if ($registry === null) {
            return null;
        }

        return [
            'id' => $registry->id,
            'name' => $registry->name,
            'primary_contacts' => $this->visiblePrimaryContacts($request, $registry),
        ];
    }

    /**
     * @return array{id: int, name: string, referent_type: array{id: int, name: string}|null, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>, is_registry_primary: bool}|null
     */
    private function summarizeReferent(Request $request): ?array
    {
        $opportunity = $this->quote?->opportunity;
        /** @var Referent|null $referent */
        $referent = $opportunity?->referent ?? $opportunity?->registry?->primaryReferent;
        $summary = ReferentRef::summarize($referent);

        if ($referent === null || $summary === null) {
            return null;
        }

        return $summary + [
            'primary_contacts' => $this->visiblePrimaryContacts($request, $referent),
            'is_registry_primary' => $opportunity->referent === null,
        ];
    }

    /**
     * @return array<int, array{type: string, icon: string|null, label: string, value: string}>
     */
    private function visiblePrimaryContacts(Request $request, Registry|Referent $owner): array
    {
        return $request->user()?->can('view', $owner) === true
            ? (new PrimaryContactColumn)->formatFor($owner)
            : [];
    }
}
