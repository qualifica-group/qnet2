<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Chat;

use App\Enums\WorkOrderType;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Services\WorkOrders\WorkOrderTypeAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The chat dock's channels (spec 0432): the project work orders where the
 * actor is Responsabile or Partecipante (D-1; a super-admin outside the team
 * is not listed), none without the Commesse di Progetto viewing permission
 * (D-2: a team member is always inside the perimeter, so the permission is
 * the whole `view` check). One query for the list with correlated subqueries
 * for the unread count and the last message id, one for those messages (D-6).
 */
final class WorkOrderChatChannels
{
    public const string UNREAD_COLUMN = 'chat_unread_count';

    public const string LAST_MESSAGE_COLUMN = 'chat_last_message_id';

    /** Relations the client name is read from (`WorkOrderReferences::registry`). */
    private const array RELATIONS = ['registry', 'quote.opportunity.registry'];

    public function __construct(private readonly WorkOrderChatUnreadQuery $unread) {}

    /**
     * @return Collection<int, WorkOrder> each with `lastChatMessage` set (null without messages)
     */
    public function forUser(User $actor): Collection
    {
        // Step 1: no viewing permission, no channel
        if (! $actor->can(WorkOrderTypeAccess::viewPermission(WorkOrderType::Project))) {
            return collect();
        }

        // Step 2: the actor's team work orders, with unread count and last message id
        $workOrders = $this->query($actor)->get();

        // Step 3: the last messages, in one query, then the D-3 order
        $this->attachLastMessages($workOrders);

        return $workOrders
            ->sortBy([
                fn (WorkOrder $a, WorkOrder $b): int => ($b->getAttribute(self::LAST_MESSAGE_COLUMN) ?? 0) <=> ($a->getAttribute(self::LAST_MESSAGE_COLUMN) ?? 0),
                fn (WorkOrder $a, WorkOrder $b): int => $b->id <=> $a->id,
            ])
            ->values();
    }

    /** @return Builder<WorkOrder> */
    private function query(User $actor): Builder
    {
        return WorkOrder::query()
            ->select('work_orders.*')
            ->where('work_orders.type', WorkOrderType::Project->value)
            ->where(fn (Builder $team) => $team
                ->whereExists($this->unread->teamMembership('work_order_supervisor', $actor))
                ->orWhereExists($this->unread->teamMembership('work_order_participant', $actor)))
            ->selectSub($this->unread->forActor($actor), self::UNREAD_COLUMN)
            ->selectSub(
                WorkOrderChatMessage::withTrashed()
                    ->select(DB::raw('max(work_order_chat_messages.id)'))
                    ->whereColumn('work_order_chat_messages.work_order_id', 'work_orders.id'),
                self::LAST_MESSAGE_COLUMN,
            )
            ->with(self::RELATIONS);
    }

    /** @param Collection<int, WorkOrder> $workOrders */
    private function attachLastMessages(Collection $workOrders): void
    {
        $ids = $workOrders->pluck(self::LAST_MESSAGE_COLUMN)->filter()->all();
        $messages = $ids === []
            ? collect()
            : WorkOrderChatMessage::withTrashed()->with('author')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($workOrders as $workOrder) {
            $workOrder->setRelation('lastChatMessage', $messages->get($workOrder->getAttribute(self::LAST_MESSAGE_COLUMN)));
        }
    }
}
