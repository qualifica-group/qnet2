<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Chat;

use App\Models\User;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The unread chat count of a work order as ONE correlated subquery on
 * `work_orders.id` (spec 0359, 0432): the messages of OTHERS, not
 * soft-deleted, newer than the actor's last read mark. It is 0 for an actor
 * outside the commessa's team (Responsabili + Partecipanti) EXCEPT a
 * super-admin, who is counted like a team member because the chat lets them
 * read and write (spec 0359 D-4). The actor id is a bound parameter.
 * Shared by the "Non letti" column and the chat dock's channel list.
 */
final class WorkOrderChatUnreadQuery
{
    /** @return Builder<WorkOrderChatMessage> */
    public function forActor(User $actor): Builder
    {
        $query = WorkOrderChatMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('work_order_chat_messages.work_order_id', 'work_orders.id')
            ->where(static fn (Builder $author) => $author
                ->whereNull('work_order_chat_messages.user_id')
                ->orWhere('work_order_chat_messages.user_id', '<>', $actor->id))
            ->where('work_order_chat_messages.id', '>', $this->lastReadSubquery($actor));

        if (! $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            $query->where(fn (Builder $team) => $team
                ->whereExists($this->teamMembership('work_order_supervisor', $actor))
                ->orWhereExists($this->teamMembership('work_order_participant', $actor)));
        }

        return $query;
    }

    /** `exists` on a team pivot (`work_order_supervisor` / `work_order_participant`) for the actor. */
    public function teamMembership(string $pivot, User $actor): QueryBuilder
    {
        return DB::table($pivot)
            ->select(DB::raw('1'))
            ->whereColumn("{$pivot}.work_order_id", 'work_orders.id')
            ->where("{$pivot}.user_id", $actor->id);
    }

    private function lastReadSubquery(User $actor): QueryBuilder
    {
        return WorkOrderChatRead::query()
            ->select(DB::raw('coalesce(max(last_read_message_id), 0)'))
            ->whereColumn('work_order_chat_reads.work_order_id', 'work_orders.id')
            ->where('work_order_chat_reads.user_id', $actor->id)
            ->toBase();
    }
}
