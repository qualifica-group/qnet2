<?php

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderChatRead;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('creates the chat tables with the expected columns', function (): void {
    expect(Schema::hasColumns('work_order_chat_messages', [
        'id', 'work_order_id', 'user_id', 'body', 'edited_at', 'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('work_order_chat_reads', [
            'id', 'work_order_id', 'user_id', 'last_read_message_id', 'created_at', 'updated_at',
        ]))->toBeTrue();
});

it('creates valid rows from the factories and wires the relations', function (): void {
    $message = WorkOrderChatMessage::factory()->create();
    $read = WorkOrderChatRead::factory()->create(['work_order_id' => $message->work_order_id]);

    expect($message->workOrder)->toBeInstanceOf(WorkOrder::class)
        ->and($message->author)->toBeInstanceOf(User::class)
        ->and($message->workOrder->chatMessages)->toHaveCount(1)
        ->and($read->workOrder->is($message->workOrder))->toBeTrue()
        ->and($read->user)->toBeInstanceOf(User::class);
});

it('soft deletes a message keeping the row', function (): void {
    $message = WorkOrderChatMessage::factory()->create();

    $message->delete();

    expect(WorkOrderChatMessage::count())->toBe(0)
        ->and(WorkOrderChatMessage::withTrashed()->count())->toBe(1);
});

it('nulls the author when the user is removed', function (): void {
    $message = WorkOrderChatMessage::factory()->create();

    $message->author->delete();

    expect($message->fresh()->user_id)->toBeNull()
        ->and($message->fresh()->author)->toBeNull();
});

it('cascades messages and reads when the work order is deleted', function (): void {
    $message = WorkOrderChatMessage::factory()->create();
    WorkOrderChatRead::factory()->create(['work_order_id' => $message->work_order_id]);

    $message->workOrder->delete();

    expect(WorkOrderChatMessage::withTrashed()->count())->toBe(0)
        ->and(WorkOrderChatRead::count())->toBe(0);
});

it('enforces one read marker per user and work order', function (): void {
    $read = WorkOrderChatRead::factory()->create();

    WorkOrderChatRead::factory()->create([
        'work_order_id' => $read->work_order_id,
        'user_id' => $read->user_id,
    ]);
})->throws(QueryException::class);

it('logs create, update and delete of a message with body before and after', function (): void {
    $message = WorkOrderChatMessage::factory()->create(['body' => 'first']);
    $message->update(['body' => 'second']);
    $message->delete();

    $logs = Activity::query()->where('subject_type', $message->getMorphClass())
        ->where('subject_id', $message->id)->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($logs[0]->properties['attributes']['body'])->toBe('first')
        ->and($logs[1]->properties['attributes']['body'])->toBe('second')
        ->and($logs[1]->properties['old']['body'])->toBe('first');
});
