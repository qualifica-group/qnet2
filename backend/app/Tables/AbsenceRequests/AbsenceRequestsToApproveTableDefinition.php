<?php

namespace App\Tables\AbsenceRequests;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Da approvare" grid (domain `absence-requests-to-approve`, spec 0278):
 * submitted requests the actor may decide — those in which they are among the
 * snapshotted approvers, or every one but their own for the HR office (D-15).
 * Rows carry the `approve` and `reject` actions when the actor can decide (spec 0304).
 */
class AbsenceRequestsToApproveTableDefinition extends AbstractAbsenceRequestsTableDefinition
{
    public function domain(): string
    {
        return 'absence-requests-to-approve';
    }

    protected function applyScope(Builder $query, User $actor): void
    {
        $query->where('absence_requests.status', AbsenceRequestStatus::Submitted->value);

        if ($this->visibility->canManageAll($actor)) {
            $query->where('absence_requests.user_id', '!=', $actor->id);

            return;
        }

        $query->whereHas('approvers', static fn (Builder $approvers) => $approvers->whereKey($actor->id));
    }

    /**
     * @return Builder<AbsenceRequest>
     */
    public function baseQuery(): Builder
    {
        return parent::baseQuery()->with('approvers:id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [
            ...parent::actions(),
            ['key' => 'approve', 'label' => 'absences.requests.actions.approve', 'icon' => 'check', 'type' => 'action', 'confirm' => true, 'permission' => 'absence-requests.viewAny'],
            ['key' => 'reject', 'label' => 'absences.requests.actions.reject', 'icon' => 'x', 'type' => 'action', 'confirm' => false, 'permission' => 'absence-requests.viewAny'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        /** @var AbsenceRequest $row */
        return $this->visibility->canDecide($actor, $row) ? [...parent::actionsFor($actor, $row), 'approve', 'reject'] : parent::actionsFor($actor, $row);
    }
}
