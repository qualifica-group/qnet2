<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Lead;
use Illuminate\Validation\ValidationException;

/**
 * D-4 of spec 0154: a Task's `lead_id`, when the Task also carries a
 * `registry_id`, must point at a Lead of THAT SAME Anagrafica — a Lead has
 * its own `registry_id` column (no pivot, unlike the referent/registry
 * relation TaskReferentRegistryGuard checks), so the rule is a direct
 * column comparison.
 *
 * A Task with no `registry_id` admits any Lead (the coherence rule has
 * nothing to compare against): only a Task that DOES carry an Anagrafica
 * narrows its Lead to that Anagrafica's own.
 *
 * Invoked by App\Services\TaskService inside the write transaction, on the
 * RESULTING pair — same convention as TaskReferentRegistryGuard, called
 * right alongside it so the two record-link coherence rules are never
 * evaluated apart.
 */
final class TaskLeadRegistryGuard
{
    /**
     * @throws ValidationException 422 on `lead_id`
     */
    public function assertBelongs(?int $registryId, ?int $leadId): void
    {
        if ($leadId === null || $registryId === null) {
            return;
        }

        $belongs = Lead::query()->whereKey($leadId)->where('registry_id', $registryId)->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'lead_id' => ['The selected lead does not belong to the selected registry.'],
            ]);
        }
    }
}
