<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Chat;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Services\RoleAssignmentGuard;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * THE single chat rule of a work order (spec 0354, D-4/D-5/D-7/D-10), shared by
 * the controller, the broadcast channel and the per-record actions.
 *
 * Deliberately NOT a Gate ability: `Gate::before` grants everything to the
 * super-admin, who would then bypass the edit window (D-5) and the closed
 * work order veto (D-7).
 */
final class WorkOrderChatAccess
{
    public function __construct(private readonly WorkOrderStatusResolver $statusResolver) {}

    /** Project work order, visible to the user, and the user is in its team or is a super-admin. */
    public function canRead(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->type === WorkOrderType::Project
            && $user->can('view', $workOrder)
            && $this->isTeamOrSuperAdmin($user, $workOrder);
    }

    /** Reading allowed and the computed status is neither completed nor closed. */
    public function canWrite(User $user, WorkOrder $workOrder): bool
    {
        return $this->canRead($user, $workOrder) && ! $this->isClosed($workOrder);
    }

    public function canModerate(User $user): bool
    {
        return $user->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    }

    /** Writing allowed, message alive, and the user moderates or is the author inside the window. */
    public function canEditMessage(User $user, WorkOrder $workOrder, WorkOrderChatMessage $message): bool
    {
        if ($message->trashed() || ! $this->canWrite($user, $workOrder)) {
            return false;
        }

        return $this->canModerate($user)
            || ($message->user_id === $user->id && now()->lessThan($this->editableUntil($message)));
    }

    public function editableUntil(WorkOrderChatMessage $message): Carbon
    {
        return $message->created_at->copy()->addMinutes((int) config('chat.edit_window_minutes'));
    }

    /**
     * The ordered read checks of every endpoint (data_contract): policy view
     * 403, not a project 404, outside the team 403.
     */
    public function assertCanRead(User $user, WorkOrder $workOrder): void
    {
        // Step 1: the work order must be visible to the user
        Gate::forUser($user)->authorize('view', $workOrder);

        // Step 2: the chat exists only on project work orders
        abort_unless($workOrder->type === WorkOrderType::Project, 404, 'Resource not found.');

        // Step 3: team members and super-admins only
        abort_unless($this->isTeamOrSuperAdmin($user, $workOrder), 403, 'You are not part of this work order team.');
    }

    /** The 409 on a completed or closed work order, run after assertCanRead() and validation. */
    public function assertOpen(WorkOrder $workOrder): void
    {
        abort_if($this->isClosed($workOrder), 409, 'This work order is closed.');
    }

    private function isTeamOrSuperAdmin(User $user, WorkOrder $workOrder): bool
    {
        return $this->canModerate($user)
            || $workOrder->supervisors()->where('users.id', $user->id)->exists()
            || $workOrder->participants()->where('users.id', $user->id)->exists();
    }

    private function isClosed(WorkOrder $workOrder): bool
    {
        return in_array($this->statusResolver->progress($workOrder)->status, [WorkOrderStatus::Completed, WorkOrderStatus::Closed], true);
    }
}
