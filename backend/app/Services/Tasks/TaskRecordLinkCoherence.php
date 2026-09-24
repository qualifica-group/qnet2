<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use Illuminate\Validation\ValidationException;

/**
 * Facade over the Task's three record-link coherence guards
 * (TaskReferentRegistryGuard spec 0101 AC-014, TaskLeadRegistryGuard spec
 * 0154 D-4, TaskWorkOrderOpportunityGuard spec 0154 D-11): split out purely
 * for App\Services\TaskService's own file size (engineering.md §6) — the
 * three rules are evaluated together, in the same order, on both write
 * paths, so a single call site is also the more accurate shape of the rule
 * ("the Task's record links are coherent") than three separate ones.
 *
 * @throws ValidationException 422 on `work_order_id`/`registry_id`/`referent_id`/`lead_id`
 */
final class TaskRecordLinkCoherence
{
    public function __construct(
        private readonly TaskReferentRegistryGuard $referentRegistryGuard,
        private readonly TaskLeadRegistryGuard $leadRegistryGuard,
        private readonly TaskWorkOrderOpportunityGuard $workOrderOpportunityGuard,
    ) {}

    /**
     * Returns the `registry_id` the Task must carry (the submitted one, or
     * the one D-11 derives from `workOrderId`), having asserted every other
     * coherence rule against it.
     */
    public function resolveRegistryId(
        ?int $registryId,
        ?int $referentId,
        ?int $leadId,
        ?int $workOrderId,
        ?int $opportunityId,
    ): ?int {
        $this->workOrderOpportunityGuard->assertExclusive($workOrderId, $opportunityId);
        $registryId = $this->workOrderOpportunityGuard->resolveRegistryId($registryId, $workOrderId);
        $this->referentRegistryGuard->assertBelongs($registryId, $referentId);
        $this->leadRegistryGuard->assertBelongs($registryId, $leadId);

        return $registryId;
    }
}
