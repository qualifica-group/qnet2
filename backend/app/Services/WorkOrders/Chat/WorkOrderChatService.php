<?php

declare(strict_types=1);

namespace App\Services\WorkOrders\Chat;

use App\Enums\WorkOrderChatAction;
use App\Events\WorkOrderChatMessageBroadcast;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes of a work order's chat (spec 0359). Every query starts from
 * an already authorized work order (D-9.b); the callers (FormRequests) have
 * run WorkOrderChatAccess::assertCanRead/assertOpen, this class adds only
 * the per-message edit rule.
 */
final class WorkOrderChatService
{
    public const int MAX_BODY_LENGTH = 2000;

    public const int DEFAULT_LIMIT = 50;

    public const int MAX_LIMIT = 100;

    public function __construct(private readonly WorkOrderChatAccess $access) {}

    /**
     * The newest `$limit` messages older than `$beforeId`, in ascending id order.
     *
     * @return array{messages: Collection<int, WorkOrderChatMessage>, has_more: bool}
     */
    public function page(WorkOrder $workOrder, ?int $beforeId, int $limit): array
    {
        $rows = $workOrder->chatMessages()
            ->withTrashed()
            ->with('author.avatar')
            ->when($beforeId !== null, fn (Builder $query) => $query->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        return [
            'messages' => $rows->take($limit)->reverse()->values(),
            'has_more' => $rows->count() > $limit,
        ];
    }

    public function lastReadMessageId(WorkOrder $workOrder, User $user): ?int
    {
        return WorkOrderChatRead::query()
            ->where('work_order_id', $workOrder->id)
            ->where('user_id', $user->id)
            ->value('last_read_message_id');
    }

    /** Other people's live messages after the user's read marker. */
    public function unreadCount(WorkOrder $workOrder, User $user, ?int $lastReadMessageId): int
    {
        return $workOrder->chatMessages()
            ->where('id', '>', $lastReadMessageId ?? 0)
            ->where(fn (Builder $query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->count();
    }

    public function post(WorkOrder $workOrder, User $author, string $body): WorkOrderChatMessage
    {
        // Step 1: persist
        $message = $workOrder->chatMessages()->create(['user_id' => $author->id, 'body' => $body]);

        // Step 2: broadcast after the commit
        return $this->broadcast(WorkOrderChatAction::Created, $message->setRelation('author', $author->loadMissing('avatar')));
    }

    public function update(WorkOrder $workOrder, User $actor, WorkOrderChatMessage $message, string $body): WorkOrderChatMessage
    {
        $this->assertCanEdit($workOrder, $actor, $message);

        $message->update(['body' => $body, 'edited_at' => now()]);

        return $this->broadcast(WorkOrderChatAction::Updated, $message);
    }

    /** Logical deletion (D-5): the body is wiped and the row soft deleted, so it stays in place. */
    public function delete(WorkOrder $workOrder, User $actor, WorkOrderChatMessage $message): WorkOrderChatMessage
    {
        $this->assertCanEdit($workOrder, $actor, $message);

        DB::transaction(function () use ($message): void {
            $message->update(['body' => null]);
            $message->delete();
        });

        return $this->broadcast(WorkOrderChatAction::Deleted, $message);
    }

    /**
     * Moves the user's read marker forward; a lower id never moves it back.
     *
     * @return array{last_read_message_id: int, unread_count: int}
     */
    public function markRead(WorkOrder $workOrder, User $user, int $lastMessageId): array
    {
        $read = WorkOrderChatRead::query()->firstOrNew(['work_order_id' => $workOrder->id, 'user_id' => $user->id]);
        $read->last_read_message_id = max((int) $read->last_read_message_id, $lastMessageId);
        $read->save();

        return [
            'last_read_message_id' => $read->last_read_message_id,
            'unread_count' => $this->unreadCount($workOrder, $user, $read->last_read_message_id),
        ];
    }

    private function assertCanEdit(WorkOrder $workOrder, User $actor, WorkOrderChatMessage $message): void
    {
        abort_unless($this->access->canEditMessage($actor, $workOrder, $message), 403, 'You cannot change this message.');
    }

    /**
     * Hands the message to the event with its author loaded (the resource reads it).
     * A realtime server that is down never fails the saved write: with a sync
     * queue the broadcast runs inside the request, so its error is only reported;
     * clients catch up when they reload or reconnect.
     */
    private function broadcast(WorkOrderChatAction $action, WorkOrderChatMessage $message): WorkOrderChatMessage
    {
        $message->loadMissing('author.avatar');

        try {
            WorkOrderChatMessageBroadcast::dispatch($action, $message);
        } catch (BroadcastException $exception) {
            report($exception);
        }

        return $message;
    }
}
