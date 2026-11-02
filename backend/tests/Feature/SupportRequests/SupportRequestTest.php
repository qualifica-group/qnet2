<?php

use App\Models\Attachment;
use App\Models\SupportTopic;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use App\Models\User;
use App\Notifications\TaskAssigned;
use App\Services\AttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| IT support requests of the end user (spec 0334, AC-004..AC-013)
|--------------------------------------------------------------------------
*/

/**
 * An active topic with one active assignee, optionally one watcher.
 *
 * @param  array<string, mixed>  $attributes
 */
function availableSupportTopic(array $attributes = [], bool $withWatcher = false): SupportTopic
{
    $topic = SupportTopic::factory()->create($attributes);
    $topic->assignees()->sync([User::factory()->create()->id]);

    if ($withWatcher) {
        $topic->watchers()->sync([User::factory()->create()->id]);
    }

    return $topic;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supportRequestPayload(SupportTopic $topic, array $overrides = []): array
{
    return ['support_topic_id' => $topic->id, 'description' => 'La stampante non stampa', ...$overrides];
}

it('AC-004: lists only the active topics with an active assignee, with the four user-facing keys', function () {
    $visible = availableSupportTopic(['name' => 'Stampante', 'description' => 'Aiuto', 'icon' => 'printer']);
    availableSupportTopic(['name' => 'Disattivo', 'is_active' => false]);
    SupportTopic::factory()->create(['name' => 'Senza assegnatari']);
    $inactiveAssignee = SupportTopic::factory()->create(['name' => 'Assegnatario inattivo']);
    $inactiveAssignee->assignees()->sync([User::factory()->create(['is_active' => false])->id]);
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/support-requests/topics')->assertOk();

    expect($response->json('data'))->toBe([
        ['id' => $visible->id, 'name' => 'Stampante', 'description' => 'Aiuto', 'icon' => 'printer'],
    ]);
});

it('AC-004: requires authentication', function () {
    $this->getJson('/api/support-requests/topics')->assertUnauthorized();
    $this->postJson('/api/support-requests', [])->assertUnauthorized();
});

it('AC-005: a user with no Task permission creates a Task configured by the topic', function () {
    $topic = availableSupportTopic([
        'task_type_id' => TaskType::factory()->create()->id,
        'task_category_id' => TaskCategory::factory()->create()->id,
        'task_priority_id' => TaskPriority::factory()->create()->id,
        'task_importance_id' => TaskImportance::factory()->create()->id,
        'is_private' => true,
        'requires_closure_feedback' => true,
        'due_days' => 4,
    ], withWatcher: true);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/support-requests', supportRequestPayload($topic))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.can_view_task', false);

    $task = Task::query()->findOrFail($response->json('data.task_id'));

    expect($task->requester_id)->toBe($user->id)
        ->and($task->creator_id)->toBe($user->id)
        ->and($task->task_type_id)->toBe($topic->task_type_id)
        ->and($task->task_category_id)->toBe($topic->task_category_id)
        ->and($task->task_priority_id)->toBe($topic->task_priority_id)
        ->and($task->task_importance_id)->toBe($topic->task_importance_id)
        ->and($task->is_private)->toBeTrue()
        ->and($task->requires_closure_feedback)->toBeTrue()
        ->and($task->end_date->toDateString())->toBe(now()->addDays(4)->toDateString())
        ->and($task->assignees()->pluck('users.id')->all())->toBe($topic->assignees()->pluck('users.id')->all())
        ->and($task->watchers()->pluck('users.id')->all())->toBe($topic->watchers()->pluck('users.id')->all());
});

it('AC-005: a watcher who is the requester is skipped instead of failing the request', function () {
    $topic = availableSupportTopic();
    $requester = User::factory()->create();
    $topic->watchers()->sync([$requester->id]);
    Sanctum::actingAs($requester);

    $id = $this->postJson('/api/support-requests', supportRequestPayload($topic))->assertCreated()->json('data.task_id');

    expect(Task::query()->findOrFail($id)->watchers()->count())->toBe(0);
});

it('AC-006: an urgent request uses the urgent priority and deadline, falling back to the normal ones', function () {
    $normal = TaskPriority::factory()->create();
    $urgent = TaskPriority::factory()->create();
    $configured = availableSupportTopic([
        'task_priority_id' => $normal->id, 'urgent_task_priority_id' => $urgent->id, 'due_days' => 5, 'urgent_due_days' => 1,
    ]);
    $plain = availableSupportTopic(['task_priority_id' => $normal->id, 'due_days' => 5]);
    Sanctum::actingAs(User::factory()->create());

    $urgentTask = Task::query()->findOrFail(
        $this->postJson('/api/support-requests', supportRequestPayload($configured, ['is_urgent' => true]))->json('data.task_id'),
    );
    $normalTask = Task::query()->findOrFail(
        $this->postJson('/api/support-requests', supportRequestPayload($configured))->json('data.task_id'),
    );
    $fallbackTask = Task::query()->findOrFail(
        $this->postJson('/api/support-requests', supportRequestPayload($plain, ['is_urgent' => true]))->json('data.task_id'),
    );

    expect($urgentTask->task_priority_id)->toBe($urgent->id)
        ->and($urgentTask->end_date->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($normalTask->task_priority_id)->toBe($normal->id)
        ->and($normalTask->end_date->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and($fallbackTask->task_priority_id)->toBe($normal->id)
        ->and($fallbackTask->end_date->toDateString())->toBe(now()->addDays(5)->toDateString());
});

it('AC-007: the title is "{topic}: {start of the description}" and never exceeds 191 characters', function () {
    $topic = availableSupportTopic(['name' => 'Stampante']);
    Sanctum::actingAs(User::factory()->create());

    $short = Task::query()->findOrFail(
        $this->postJson('/api/support-requests', supportRequestPayload($topic, ['description' => "Non stampa\npiu' nulla"]))->json('data.task_id'),
    );
    $long = Task::query()->findOrFail(
        $this->postJson('/api/support-requests', supportRequestPayload($topic, ['description' => str_repeat('a', 5000)]))->json('data.task_id'),
    );

    expect($short->title)->toBe("Stampante: Non stampa piu' nulla")
        ->and(mb_strlen($long->title))->toBe(191)
        ->and($long->title)->toStartWith('Stampante: aaa');
});

it('AC-008: HTML in the description is escaped and page/browser context is appended', function () {
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $id = $this->postJson('/api/support-requests', supportRequestPayload($topic, [
        'description' => '<script>alert(1)</script> <b>grassetto</b>',
        'page_url' => '/tasks?x=1',
        'user_agent' => 'Mozilla/5.0 <Test>',
    ]))->assertCreated()->json('data.task_id');

    $description = Task::query()->findOrFail($id)->description;

    expect($description)->not->toContain('<script>')
        ->and($description)->not->toContain('<b>')
        ->and($description)->toContain('&lt;script&gt;')
        ->and($description)->toContain('/tasks?x=1')
        ->and($description)->toContain('Mozilla/5.0 &lt;Test&gt;');
});

it('AC-009: two valid attachments are stored on the Task', function () {
    Storage::fake(config('attachments.disk'));
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $id = $this->postJson('/api/support-requests', supportRequestPayload($topic, [
        'attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')],
    ]))->assertCreated()->json('data.task_id');

    expect(Attachment::query()->where('attachable_id', $id)->count())->toBe(2);
});

it('AC-009: too many, too big or disallowed attachments give 422 and no Task', function (array $attachments) {
    Storage::fake(config('attachments.disk'));
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/support-requests', supportRequestPayload($topic, ['attachments' => $attachments]))
        ->assertUnprocessable();

    expect(Task::query()->count())->toBe(0);
})->with([
    'six files' => fn () => array_map(fn (int $i) => UploadedFile::fake()->image("f{$i}.png"), range(1, 6)),
    'over max_size' => fn () => [UploadedFile::fake()->create('big.pdf', (int) config('attachments.max_size') + 1, 'application/pdf')],
    'allowed MIME but disallowed extension' => fn () => [UploadedFile::fake()->create('script.php', 10, 'application/pdf')],
    'disallowed type' => fn () => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
]);

it('AC-010: payload keys that configure the Task are prohibited', function (string $key, mixed $value) {
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/support-requests', supportRequestPayload($topic, [$key => $value]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($key);

    expect(Task::query()->count())->toBe(0);
})->with([
    'assignee_ids' => ['assignee_ids', [1]],
    'task_priority_id' => ['task_priority_id', 1],
    'title' => ['title', 'Mio titolo'],
    'requester_id' => ['requester_id', 1],
    'is_private' => ['is_private', true],
]);

it('AC-011: an inactive, missing or unassigned topic gives 422 and no Task', function () {
    Sanctum::actingAs(User::factory()->create());
    $inactive = availableSupportTopic(['is_active' => false]);
    $unassigned = SupportTopic::factory()->create();

    $this->postJson('/api/support-requests', supportRequestPayload($inactive))
        ->assertUnprocessable()->assertJsonValidationErrors('support_topic_id');
    $this->postJson('/api/support-requests', ['support_topic_id' => 999999, 'description' => 'x'])
        ->assertUnprocessable()->assertJsonValidationErrors('support_topic_id');
    $this->postJson('/api/support-requests', supportRequestPayload($unassigned))
        ->assertUnprocessable()->assertJsonValidationErrors('support_topic_id');

    expect(Task::query()->count())->toBe(0);
});

it('AC-011: an empty description gives 422', function () {
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/support-requests', supportRequestPayload($topic, ['description' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors('description');
});

it('AC-012: the assignees receive the Task assignment notification', function () {
    $topic = availableSupportTopic();
    $assignee = $topic->assignees()->firstOrFail();
    Sanctum::actingAs(User::factory()->create());
    Notification::fake();

    $this->postJson('/api/support-requests', supportRequestPayload($topic))->assertCreated();

    Notification::assertSentTo($assignee, TaskAssigned::class);
});

it('AC-013: can_view_task is true with tasks.view and false without', function () {
    $topic = availableSupportTopic();
    $withView = taskActorWith(['view']);
    Sanctum::actingAs($withView);

    $this->postJson('/api/support-requests', supportRequestPayload($topic))
        ->assertCreated()->assertJsonPath('data.can_view_task', true);

    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/support-requests', supportRequestPayload($topic))
        ->assertCreated()->assertJsonPath('data.can_view_task', false);
});

it('AC-009: a failure on the second attachment leaves no Task, no Attachment and no file on disk', function () {
    Storage::fake(config('attachments.disk'));
    $topic = availableSupportTopic();
    Sanctum::actingAs(User::factory()->create());

    $real = app(AttachmentService::class);
    $calls = 0;
    $this->mock(AttachmentService::class, function ($mock) use ($real, &$calls) {
        $mock->shouldReceive('storeFor')->andReturnUsing(function ($owner, $file, $collection) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new RuntimeException('disk failure');
            }

            return $real->storeFor($owner, $file, $collection);
        });
    });

    $this->postJson('/api/support-requests', supportRequestPayload($topic, [
        'attachments' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')],
    ]))->assertStatus(500);

    expect($calls)->toBe(2)
        ->and(Task::query()->count())->toBe(0)
        ->and(Attachment::query()->count())->toBe(0)
        ->and(Storage::disk(config('attachments.disk'))->allFiles())->toBe([]);
});
