<?php

use App\Enums\WorkOrderChatAction;
use App\Events\WorkOrderChatMessageBroadcast;
use App\Http\Resources\WorkOrderChatMessageResource;
use App\Models\Attachment;
use App\Models\WorkOrderChatMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/../../Helpers/WorkOrderChatHelpers.php';

uses(RefreshDatabase::class);

it('AC-005: an empty, blank or oversized body is a 422 and writes nothing', function (string $body): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id, 'body' => 'Original']);
    Sanctum::actingAs($member);

    $this->postJson(chatUrl($workOrder), ['body' => $body])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => $body])->assertUnprocessable();

    expect(WorkOrderChatMessage::count())->toBe(1)
        ->and($message->fresh()->body)->toBe('Original')
        ->and($message->fresh()->edited_at)->toBeNull();
})->with([
    'empty' => '',
    'blank' => '    ',
    'too long' => fn () => str_repeat('a', 2001),
]);

it('accepts a body of exactly 2000 characters and keeps markup as plain text', function (): void {
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->postJson(chatUrl($workOrder), ['body' => str_repeat('a', 2000)])->assertCreated();
    $this->postJson(chatUrl($workOrder), ['body' => '<b>bold</b>'])->assertCreated()->assertJsonPath('data.body', '<b>bold</b>');
});

it('AC-006: a created message dispatches the chat.message event with the ChatMessage payload', function (): void {
    Event::fake([WorkOrderChatMessageBroadcast::class]);
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);

    $response = $this->postJson(chatUrl($workOrder), ['body' => '  Hello team  '])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.body', 'Hello team')
        ->assertJsonPath('data.author.id', $member->id)
        ->assertJsonPath('data.is_deleted', false)
        ->assertJsonPath('data.edited_at', null);

    Event::assertDispatchedTimes(WorkOrderChatMessageBroadcast::class, 1);
    Event::assertDispatched(WorkOrderChatMessageBroadcast::class, function (WorkOrderChatMessageBroadcast $event) use ($response, $workOrder): bool {
        return $event->action === WorkOrderChatAction::Created
            && $event->broadcastAs() === 'chat.message'
            && $event->broadcastOn()[0]->name === "private-work-orders.{$workOrder->id}.chat"
            && $event->broadcastWith() === ['action' => 'created', 'message' => $response->json('data')];
    });
});

it('AC-006: a failed write dispatches no event', function (): void {
    Event::fake([WorkOrderChatMessageBroadcast::class]);
    $workOrder = chatWorkOrder();
    Sanctum::actingAs(chatMember($workOrder));

    $this->postJson(chatUrl($workOrder), ['body' => ''])->assertUnprocessable();
    $this->postJson(chatUrl(chatClose($workOrder)), ['body' => 'Hi'])->assertStatus(409)->assertJsonPath('success', false);

    Event::assertNothingDispatched();
});

it('AC-006: the event is queued, after commit and on the private channel', function (): void {
    $event = new WorkOrderChatMessageBroadcast(WorkOrderChatAction::Created, WorkOrderChatMessage::factory()->create());

    expect($event)->toBeInstanceOf(ShouldBroadcast::class)
        ->and($event)->not->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event)->toBeInstanceOf(ShouldDispatchAfterCommit::class);
});

it('AC-006: the broadcast payload carries a null avatar_url while REST keeps the avatar', function (): void {
    Storage::fake('local');
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    $message->load('author.avatar');
    $message->author->setRelation('avatar', new Attachment(['disk' => 'local', 'path' => 'avatars/a.png']));
    Storage::disk('local')->put('avatars/a.png', 'png-bytes');

    $rest = (new WorkOrderChatMessageResource($message))->resolve();
    $event = new WorkOrderChatMessageBroadcast(WorkOrderChatAction::Created, $message);

    expect($rest['author']['avatar_url'])->not->toBeNull()
        ->and($event->broadcastWith()['message']['author']['avatar_url'])->toBeNull()
        ->and($event->broadcastWith()['message']['author']['id'])->toBe($member->id);
});

it('AC-007: the author edits within the window, not after it', function (): void {
    Event::fake([WorkOrderChatMessageBroadcast::class]);
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    Sanctum::actingAs($member);

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Fixed'])
        ->assertOk()
        ->assertJsonPath('data.body', 'Fixed');

    expect($message->fresh()->edited_at)->not->toBeNull();
    Event::assertDispatched(WorkOrderChatMessageBroadcast::class, fn ($event) => $event->action === WorkOrderChatAction::Updated);

    $this->travel(16)->minutes();

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Too late'])->assertForbidden();
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertForbidden();
    expect($message->fresh()->body)->toBe('Fixed');
});

it('AC-007: the edit window follows config chat.edit_window_minutes', function (): void {
    config(['chat.edit_window_minutes' => 1]);
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    Sanctum::actingAs($member);

    $this->travel(2)->minutes();

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Late'])->assertForbidden();
});

it('AC-008: the author deletes logically, the row stays and the list shows it as deleted', function (): void {
    Event::fake([WorkOrderChatMessageBroadcast::class]);
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $member->id]);
    Sanctum::actingAs($member);

    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))
        ->assertOk()
        ->assertJsonPath('data.is_deleted', true)
        ->assertJsonPath('data.body', null);

    expect(WorkOrderChatMessage::withTrashed()->find($message->id))->not->toBeNull()
        ->and(WorkOrderChatMessage::withTrashed()->find($message->id)->body)->toBeNull();
    Event::assertDispatched(WorkOrderChatMessageBroadcast::class, fn ($event) => $event->action === WorkOrderChatAction::Deleted);

    $this->getJson(chatUrl($workOrder))
        ->assertOk()
        ->assertJsonPath('data.messages.0.id', $message->id)
        ->assertJsonPath('data.messages.0.is_deleted', true)
        ->assertJsonPath('data.messages.0.body', null);

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Back'])->assertNotFound();
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertNotFound();
});

it('AC-009: a member cannot touch another author message, a super-admin can beyond the window', function (): void {
    Event::fake([WorkOrderChatMessageBroadcast::class]);
    $workOrder = chatWorkOrder();
    $author = chatMember($workOrder);
    $colleague = chatMember($workOrder, 'participants');
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id, 'user_id' => $author->id, 'body' => 'Mine']);

    Sanctum::actingAs($colleague);
    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Hacked'])->assertForbidden();
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertForbidden();
    expect($message->fresh()->body)->toBe('Mine');

    $this->travel(60)->minutes();
    $admin = chatSuperAdmin();
    Sanctum::actingAs($admin);

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Moderated'])->assertOk();
    Event::assertDispatched(WorkOrderChatMessageBroadcast::class, fn ($event) => $event->action === WorkOrderChatAction::Updated);
    $log = Activity::query()->where('subject_id', $message->id)->where('causer_id', $admin->id)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->properties['old']['body'])->toBe('Mine')
        ->and($log->properties['attributes']['body'])->toBe('Moderated');

    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertOk()->assertJsonPath('data.is_deleted', true);
    Event::assertDispatched(WorkOrderChatMessageBroadcast::class, fn ($event) => $event->action === WorkOrderChatAction::Deleted);
});

it('AC-009: the closed work order veto holds for the super-admin too', function (): void {
    $workOrder = chatClose(chatWorkOrder());
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(chatSuperAdmin());

    $this->patchJson(chatUrl($workOrder, "messages/{$message->id}"), ['body' => 'Edit'])->assertStatus(409);
    $this->deleteJson(chatUrl($workOrder, "messages/{$message->id}"))->assertStatus(409);
    $this->postJson(chatUrl($workOrder), ['body' => 'Hi'])->assertStatus(409);
});

it('AC-010: a message of work order A is 404 through work order B', function (): void {
    $workOrderA = chatWorkOrder();
    $workOrderB = chatWorkOrder();
    $member = chatMember($workOrderB);
    $message = WorkOrderChatMessage::factory()->create(['work_order_id' => $workOrderA->id, 'user_id' => $member->id, 'body' => 'Keep']);
    Sanctum::actingAs($member);

    $this->patchJson(chatUrl($workOrderB, "messages/{$message->id}"), ['body' => 'Hi'])->assertNotFound();
    $this->deleteJson(chatUrl($workOrderB, "messages/{$message->id}"))->assertNotFound();
    expect($message->fresh()->body)->toBe('Keep');
});

it('AC-024: create, edit and delete each write an activity log entry with before and after', function (): void {
    $workOrder = chatWorkOrder();
    $member = chatMember($workOrder);
    Sanctum::actingAs($member);

    $id = $this->postJson(chatUrl($workOrder), ['body' => 'One'])->assertCreated()->json('data.id');
    $this->patchJson(chatUrl($workOrder, "messages/{$id}"), ['body' => 'Two'])->assertOk();
    $this->deleteJson(chatUrl($workOrder, "messages/{$id}"))->assertOk();

    $logs = Activity::query()->where('subject_id', $id)->where('subject_type', 'work_order_chat_message')->orderBy('id')->get();

    expect($logs->pluck('event')->all())->toContain('created', 'updated', 'deleted')
        ->and($logs->every(fn (Activity $log): bool => $log->causer_id === $member->id))->toBeTrue()
        ->and($logs->first()->properties['attributes']['body'])->toBe('One');

    $edit = $logs->first(fn (Activity $log): bool => ($log->properties['attributes']['body'] ?? null) === 'Two');
    expect($edit->properties['old']['body'])->toBe('One');
});
