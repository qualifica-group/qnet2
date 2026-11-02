<?php

use App\Models\SupportTopic;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Support topics configuration (spec 0334, AC-001..AC-003)
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function supportTopicPayload(array $overrides = []): array
{
    return [
        'name' => 'Stampante',
        'description' => 'Problemi di stampa',
        'icon' => 'printer',
        'task_type_id' => TaskType::factory()->create()->id,
        'task_category_id' => TaskCategory::factory()->create()->id,
        'task_priority_id' => TaskPriority::factory()->create()->id,
        'urgent_task_priority_id' => TaskPriority::factory()->create()->id,
        'task_importance_id' => TaskImportance::factory()->create()->id,
        'due_days' => 3,
        'urgent_due_days' => 0,
        'is_private' => true,
        'requires_closure_feedback' => true,
        'assignee_ids' => [User::factory()->create()->id],
        'watcher_ids' => [User::factory()->create()->id],
        ...$overrides,
    ];
}

it('AC-001: store returns 201 and show gives back the same values, assignees and watchers', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['create', 'view']));
    $payload = supportTopicPayload();

    $id = $this->postJson('/api/support-topics', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Stampante')
        ->json('data.id');

    $this->getJson("/api/support-topics/{$id}")
        ->assertOk()
        ->assertJsonPath('data.icon', 'printer')
        ->assertJsonPath('data.due_days', 3)
        ->assertJsonPath('data.urgent_due_days', 0)
        ->assertJsonPath('data.is_private', true)
        ->assertJsonPath('data.requires_closure_feedback', true)
        ->assertJsonPath('data.task_type.id', $payload['task_type_id'])
        ->assertJsonPath('data.urgent_task_priority.id', $payload['urgent_task_priority_id'])
        ->assertJsonPath('data.assignees.0.id', $payload['assignee_ids'][0])
        ->assertJsonPath('data.watchers.0.id', $payload['watcher_ids'][0]);
});

it('AC-001: update changes values and replaces the assignees', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['update', 'view']));
    $topic = SupportTopic::factory()->create();
    $topic->assignees()->sync([User::factory()->create()->id]);
    $newAssignee = User::factory()->create();

    $this->patchJson("/api/support-topics/{$topic->id}", ['due_days' => 5, 'assignee_ids' => [$newAssignee->id]])
        ->assertOk()
        ->assertJsonPath('data.due_days', 5)
        ->assertJsonPath('data.assignees.0.id', $newAssignee->id);

    expect($topic->assignees()->count())->toBe(1);
});

it('AC-001: destroy removes the topic', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['delete']));
    $topic = SupportTopic::factory()->create();

    $this->deleteJson("/api/support-topics/{$topic->id}")->assertOk()->assertJsonPath('success', true);

    $this->assertDatabaseMissing('support_topics', ['id' => $topic->id]);
});

it('AC-002: a user without support-topics permissions gets 403 on show/store/update/destroy/rows', function () {
    Sanctum::actingAs(User::factory()->create());
    $topic = SupportTopic::factory()->create();

    $this->getJson("/api/support-topics/{$topic->id}")->assertForbidden();
    $this->postJson('/api/support-topics', supportTopicPayload())->assertForbidden();
    $this->patchJson("/api/support-topics/{$topic->id}", ['due_days' => 1])->assertForbidden();
    $this->deleteJson("/api/support-topics/{$topic->id}")->assertForbidden();
    $this->postJson('/api/tables/support-topics/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('AC-002: the SSRM rows list topics for a viewer', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['viewAny', 'view']));
    $topic = SupportTopic::factory()->create(['name' => 'PC lento']);
    $topic->assignees()->sync([User::factory()->create(['name' => 'Mario IT'])->id]);

    $this->postJson('/api/tables/support-topics/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonPath('items.0.name', 'PC lento')
        ->assertJsonPath('items.0.assignees', 'Mario IT');
});

it('AC-003: empty assignee_ids is a 422 and nothing is written', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['create', 'update']));

    $this->postJson('/api/support-topics', supportTopicPayload(['assignee_ids' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assignee_ids');
    $this->assertDatabaseCount('support_topics', 0);

    $topic = SupportTopic::factory()->create();
    $topic->assignees()->sync([User::factory()->create()->id]);

    $this->patchJson("/api/support-topics/{$topic->id}", ['assignee_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assignee_ids');
    expect($topic->assignees()->count())->toBe(1);
});

it('AC-003: a user both assignee and watcher is a 422 on watcher_ids and nothing is written', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['create', 'update']));
    $user = User::factory()->create();

    $this->postJson('/api/support-topics', supportTopicPayload(['assignee_ids' => [$user->id], 'watcher_ids' => [$user->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('watcher_ids');
    $this->assertDatabaseCount('support_topics', 0);

    $topic = SupportTopic::factory()->create();
    $topic->assignees()->sync([User::factory()->create()->id]);

    $this->patchJson("/api/support-topics/{$topic->id}", ['watcher_ids' => [$topic->assignees()->first()->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('watcher_ids');
    expect($topic->watchers()->count())->toBe(0);
});

it('rejects an inactive user as assignee and a duplicated name', function () {
    Sanctum::actingAs(taskConfigActorWith('support-topics', ['create']));
    SupportTopic::factory()->create(['name' => 'Stampante']);
    $inactive = User::factory()->create(['is_active' => false]);

    $this->postJson('/api/support-topics', supportTopicPayload(['name' => 'Stampante']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
    $this->postJson('/api/support-topics', supportTopicPayload(['name' => 'Altro', 'assignee_ids' => [$inactive->id]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('assignee_ids.0');
});
