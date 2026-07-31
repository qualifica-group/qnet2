<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use App\Models\OpportunityWorkflowStatus;
use Illuminate\Support\Collection;

/**
 * The wire projection of an OpportunityWorkflowStatus as the request-management
 * module exposes it. Extracted so the work panel
 * (RequestManagementResource) and the create form's context preview
 * (RequestFormContextResource) can never drift: the create form renders its
 * status select from the second and then submits against a record read through
 * the first, so a single missing key (`requires_note`) would silently disable
 * the mandatory-note rule on one channel only.
 */
trait SummarizesWorkflowStatuses
{
    /**
     * @return array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, requires_note: bool}|null
     */
    protected function summarizeWorkflowStatus(?OpportunityWorkflowStatus $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'description' => $status->description,
            'color' => $status->color,
            'system_key' => $status->system_key,
            'requires_note' => $status->requires_note,
        ];
    }

    /**
     * @param  Collection<int, OpportunityWorkflowStatus>  $statuses
     * @return array<int, array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, requires_note: bool}>
     */
    protected function summarizeWorkflowStatuses(Collection $statuses): array
    {
        return $statuses
            ->map(fn (OpportunityWorkflowStatus $status): array => $this->summarizeWorkflowStatus($status))
            ->values()
            ->all();
    }
}
