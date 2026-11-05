<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use App\Tables\WorkOrdersTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Table definition for the `project-work-orders` domain (spec 0359, D-1):
 * the `work-orders` grid (same columns, actions, allow-lists, visibility
 * scope and `viewAny`, all inherited) restricted to `type = project`, plus
 * the read-only `chat_unread_count` column.
 *
 * `chat_unread_count` counts the chat messages of OTHERS (not soft-deleted,
 * newer than the actor's last read mark) as one correlated subquery, never a
 * query per row. It is 0 for an actor outside the commessa's team
 * (Responsabili + Partecipanti) EXCEPT a super-admin, who is counted like a
 * team member because the chat lets them read and write (D-4). The actor id
 * is a bound parameter; the sort uses the selected alias, so it needs no
 * raw SQL.
 */
class ProjectWorkOrdersTableDefinition extends WorkOrdersTableDefinition
{
    public const string UNREAD_COLUMN = 'chat_unread_count';

    private const string SUPER_ADMIN_ROLE = 'super-admin';

    public function domain(): string
    {
        return 'project-work-orders';
    }

    /**
     * Permissions, field catalogue and inline-edit rights are the work orders' own.
     */
    public function resource(): string
    {
        return 'work-orders';
    }

    /**
     * @return Builder<WorkOrder>
     */
    public function baseQuery(): Builder
    {
        $query = parent::baseQuery()->where('work_orders.type', WorkOrderType::Project->value);

        $actor = Auth::user();

        return $actor === null ? $query : $query->selectSub($this->unreadSubquery($actor), self::UNREAD_COLUMN);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            ...parent::columns(),
            [
                'id' => self::UNREAD_COLUMN,
                'label' => 'workOrders.columns.chat_unread_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
                'hasFilterValues' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        return [
            ...parent::mapRow($actor, $row),
            self::UNREAD_COLUMN => (int) ($row->getAttribute(self::UNREAD_COLUMN) ?? 0),
        ];
    }

    private function unreadSubquery(User $actor): Builder
    {
        $query = WorkOrderChatMessage::query()
            ->selectRaw('count(*)')
            ->whereColumn('work_order_chat_messages.work_order_id', 'work_orders.id')
            ->where(static fn (Builder $author) => $author
                ->whereNull('work_order_chat_messages.user_id')
                ->orWhere('work_order_chat_messages.user_id', '<>', $actor->id))
            ->where('work_order_chat_messages.id', '>', $this->lastReadSubquery($actor));

        if (! $actor->hasRole(self::SUPER_ADMIN_ROLE)) {
            $query->where(fn (Builder $team) => $team
                ->whereExists($this->teamMembership('work_order_supervisor', $actor))
                ->orWhereExists($this->teamMembership('work_order_participant', $actor)));
        }

        return $query;
    }

    private function lastReadSubquery(User $actor): QueryBuilder
    {
        return WorkOrderChatRead::query()
            ->select(DB::raw('coalesce(max(last_read_message_id), 0)'))
            ->whereColumn('work_order_chat_reads.work_order_id', 'work_orders.id')
            ->where('work_order_chat_reads.user_id', $actor->id)
            ->toBase();
    }

    private function teamMembership(string $pivot, User $actor): QueryBuilder
    {
        return DB::table($pivot)
            ->select(DB::raw('1'))
            ->whereColumn("{$pivot}.work_order_id", 'work_orders.id')
            ->where("{$pivot}.user_id", $actor->id);
    }
}
