<?php

declare(strict_types=1);

namespace App\Events;

use App\Broadcasting\WorkOrderChatChannel;
use App\Enums\WorkOrderChatAction;
use App\Http\Resources\WorkOrderChatMessageResource;
use App\Models\WorkOrderChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A chat write, pushed to the work order's private channel (spec 0352): queued
 * and dispatched after the commit, so a rolled back write never reaches the
 * socket. The payload is frozen at dispatch time, identical to the REST shape.
 */
final class WorkOrderChatMessageBroadcast implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public const string BROADCAST_AS = 'chat.message';

    /** @var array<string, mixed> */
    public readonly array $message;

    public function __construct(
        public readonly WorkOrderChatAction $action,
        WorkOrderChatMessage $message,
    ) {
        $this->message = (new WorkOrderChatMessageResource($message))->withoutAvatar()->resolve();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(WorkOrderChatChannel::name((int) $this->message['work_order_id']))];
    }

    public function broadcastAs(): string
    {
        return self::BROADCAST_AS;
    }

    /**
     * @return array{action: string, message: array<string, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['action' => $this->action->value, 'message' => $this->message];
    }
}
