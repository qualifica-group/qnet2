<?php

use App\Models\Note;
use App\Models\Registry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\NoteMentionNotification;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Mentions outside the record (spec 0353)
|--------------------------------------------------------------------------
|
| Whoever may edit a commessa or a task can mention a colleague who cannot
| read it yet; saving the note enlists that person (Partecipante / Osservatore)
| and notifies them once. Actors never hold `viewAll`, so "outside" is real.
*/

if (! function_exists('outsideMentionUser')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function outsideMentionUser(array $permissions = [], array $attributes = []): User
    {
        foreach (['work-orders', 'tasks', 'registries'] as $module) {
            foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
                Permission::findOrCreate("{$module}.{$ability}");
            }
        }

        Permission::findOrCreate('notes.create');

        $user = User::factory()->create($attributes);
        $user->givePermissionTo($permissions);

        return $user;
    }

    function outsideMentionSpan(User $user): string
    {
        return sprintf('<span data-type="mention" data-id="%d" data-label="%s">@%s</span>', $user->id, $user->name, $user->name);
    }

    /**
     * @return array{0: User, 1: WorkOrder}
     */
    function outsideWorkOrder(array $abilities = ['work-orders.view', 'work-orders.update', 'notes.create']): array
    {
        $editor = outsideMentionUser($abilities);
        $workOrder = WorkOrder::factory()->create();
        $workOrder->supervisors()->attach($editor);

        return [$editor, $workOrder];
    }

    /**
     * @return array{0: User, 1: Task}
     */
    function outsideTask(array $abilities = ['tasks.view', 'tasks.update', 'notes.create']): array
    {
        $editor = outsideMentionUser($abilities);

        return [$editor, Task::factory()->forCreator($editor)->create()];
    }

    function outsidePost(object $test, string $type, int $id, User ...$mentioned): TestResponse
    {
        return $test->postJson('/api/notes', [
            'entity_type' => $type,
            'entity_id' => $id,
            'body' => 'Hi '.implode(' ', array_map('outsideMentionSpan', $mentioned)),
            'mentions' => array_map(fn (User $u): int => $u->id, $mentioned),
        ]);
    }
}

// AC-001
it('AC-001: meta.mention_outside reflects module support and the right to edit', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    $reader = outsideMentionUser(['work-orders.view', 'notes.create']);
    $workOrder->participants()->attach($reader, ['position' => 1]);

    Sanctum::actingAs($editor);
    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()->assertJsonPath('meta.mention_outside', ['allowed' => true, 'role' => 'participant']);

    Sanctum::actingAs($reader);
    $this->getJson("/api/notes?entity_type=work-orders&entity_id={$workOrder->id}")
        ->assertOk()->assertJsonPath('meta.mention_outside', ['allowed' => false, 'role' => 'participant']);

    [$taskEditor, $task] = outsideTask();
    Sanctum::actingAs($taskEditor);
    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->assertJsonPath('meta.mention_outside', ['allowed' => true, 'role' => 'watcher']);

    $registryReader = outsideMentionUser(['registries.view', 'notes.create']);
    $registry = Registry::factory()->create();
    Sanctum::actingAs($registryReader);
    $this->getJson("/api/notes?entity_type=registries&entity_id={$registry->id}")
        ->assertOk()->assertJsonPath('meta.mention_outside', ['allowed' => false, 'role' => null]);
});

// AC-002
it('AC-002: scope omitted or record gives today\'s list with both flags true', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    $stranger = outsideMentionUser(['work-orders.view']);
    Sanctum::actingAs($editor);

    foreach (['', '&scope=record'] as $suffix) {
        $items = $this->getJson("/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}{$suffix}")
            ->assertOk()->json('items');

        expect(collect($items)->pluck('id')->all())->toBe([$editor->id])
            ->and($items[0]['in_record'])->toBeTrue()
            ->and($items[0]['has_module_access'])->toBeTrue();
    }
});

// AC-003
it('AC-003: scope extended lists every active non-service user with the flags', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    $withAccess = outsideMentionUser(['work-orders.view']);
    $noAccess = outsideMentionUser();
    $inactive = outsideMentionUser([], ['is_active' => false]);
    $service = outsideMentionUser([], ['is_service_account' => true]);
    Sanctum::actingAs($editor);

    $items = collect($this->getJson("/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}&scope=extended&limit=100")
        ->assertOk()->json('items'))->keyBy('id');

    expect($items->keys()->all())->toContain($editor->id, $withAccess->id, $noAccess->id)
        ->not->toContain($inactive->id)->not->toContain($service->id)
        ->and($items[$editor->id]['in_record'])->toBeTrue()
        ->and($items[$withAccess->id]['in_record'])->toBeFalse()
        ->and($items[$withAccess->id]['has_module_access'])->toBeTrue()
        ->and($items[$noAccess->id]['has_module_access'])->toBeFalse();

    [$taskEditor, $task] = outsideTask();
    Sanctum::actingAs($taskEditor);
    $taskItems = collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}&scope=extended&limit=100")
        ->assertOk()->json('items'))->keyBy('id');
    expect($taskItems[$withAccess->id]['has_module_access'])->toBeFalse()
        ->and($taskItems[$taskEditor->id]['has_module_access'])->toBeTrue();
});

// AC-004
it('AC-004: scope extended is 403 without edit rights, on unsupported modules and on unreadable records; unknown scope is 422', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    $reader = outsideMentionUser(['work-orders.view']);
    $workOrder->participants()->attach($reader, ['position' => 1]);
    $url = "/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}";

    Sanctum::actingAs($reader);
    $this->getJson("{$url}&scope=extended")->assertForbidden();

    $registry = Registry::factory()->create();
    Sanctum::actingAs(outsideMentionUser(['registries.view']));
    $this->getJson("/api/notes/mentionable-users?entity_type=registries&entity_id={$registry->id}&scope=extended")->assertForbidden();

    Sanctum::actingAs(outsideMentionUser(['work-orders.view', 'work-orders.update']));
    $this->getJson("{$url}&scope=extended")->assertForbidden();

    Sanctum::actingAs($editor);
    $this->getJson("{$url}&scope=other")->assertStatus(422)->assertJsonValidationErrors('scope');
});

// AC-005
it('AC-005: an outsider mentioned on a commessa becomes Partecipante and gets one enriched notification', function () {
    Notification::fake();
    [$editor, $workOrder] = outsideWorkOrder();
    $workOrder->participants()->attach(outsideMentionUser(), ['position' => 1]);
    $outsider = outsideMentionUser(['work-orders.view']);
    Sanctum::actingAs($editor);

    $response = outsidePost($this, 'work-orders', $workOrder->id, $outsider)
        ->assertCreated()->assertJsonPath('meta.enlisted_user_ids', [$outsider->id]);

    expect(DB::table('work_order_participant')->where('work_order_id', $workOrder->id)->where('user_id', $outsider->id)->value('position'))->toBe(2);
    Notification::assertSentToTimes($outsider, NoteMentionNotification::class, 1);
    Notification::assertSentTo($outsider, NoteMentionNotification::class, function ($n) use ($outsider) {
        return str_contains($n->toArray($outsider)['message'], 'added you as Participant');
    });
    Notification::assertCount(1);
    expect($response->json('success'))->toBeTrue();
});

// AC-006
it('AC-006: an outsider mentioned on a task becomes Osservatore and can then read it', function () {
    Notification::fake();
    [$editor, $task] = outsideTask();
    $task->update(['is_private' => true]);
    $outsider = outsideMentionUser(['tasks.view']);
    Sanctum::actingAs($editor);

    outsidePost($this, 'tasks', $task->id, $outsider)
        ->assertCreated()->assertJsonPath('meta.enlisted_user_ids', [$outsider->id]);

    expect($task->watchers()->pluck('users.id')->all())->toBe([$outsider->id]);
    Notification::assertSentTo($outsider, NoteMentionNotification::class, fn ($n) => str_contains($n->toArray($outsider)['message'], 'added you as Watcher'));

    Sanctum::actingAs($outsider);
    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertOk();
});

// AC-007
it('AC-007: an author without update gets 422 and nothing changes', function () {
    Notification::fake();
    [$editor, $workOrder] = outsideWorkOrder();
    $reader = outsideMentionUser(['work-orders.view', 'notes.create']);
    $workOrder->participants()->attach($reader, ['position' => 1]);
    $outsider = outsideMentionUser(['work-orders.view']);
    Sanctum::actingAs($reader);

    outsidePost($this, 'work-orders', $workOrder->id, $outsider)
        ->assertStatus(422)->assertJsonValidationErrors('mentions');

    expect(Note::query()->count())->toBe(0)
        ->and(DB::table('work_order_participant')->where('user_id', $outsider->id)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

// AC-008
it('AC-008: a full commessa refuses the note and enlists nobody', function () {
    Notification::fake();
    [$editor, $workOrder] = outsideWorkOrder();
    foreach (range(1, ManagerPositions::MAX) as $position) {
        $workOrder->participants()->attach(outsideMentionUser(), ['position' => $position]);
    }
    $outsider = outsideMentionUser(['work-orders.view'], ['name' => 'Latecomer Smith']);
    Sanctum::actingAs($editor);

    $response = outsidePost($this, 'work-orders', $workOrder->id, $outsider)
        ->assertStatus(422)->assertJsonValidationErrors('mentions');

    expect($response->json('errors.mentions.0'))->toContain('Latecomer Smith')
        ->and(Note::query()->count())->toBe(0)
        ->and(DB::table('work_order_participant')->where('work_order_id', $workOrder->id)->count())->toBe(ManagerPositions::MAX);
    Notification::assertNothingSent();
});

// AC-009
it('AC-009: an outsider without module access is enlisted but gets no link', function () {
    Notification::fake();
    [$editor, $workOrder] = outsideWorkOrder();
    $outsider = outsideMentionUser();
    Sanctum::actingAs($editor);

    outsidePost($this, 'work-orders', $workOrder->id, $outsider)->assertCreated();

    expect(DB::table('work_order_participant')->where('user_id', $outsider->id)->exists())->toBeTrue();
    Notification::assertSentTo($outsider, NoteMentionNotification::class, function ($n) use ($outsider) {
        $payload = $n->toArray($outsider);

        return $payload['action_url'] === null
            && str_contains($payload['message'], 'ask an administrator to grant you access')
            && $n->toMail($outsider)->actionUrl === null;
    });
});

// AC-010
it('AC-010: mentioning someone already in the perimeter enlists nobody', function () {
    Notification::fake();
    [$editor, $workOrder] = outsideWorkOrder();
    $member = outsideMentionUser(['work-orders.view']);
    $workOrder->participants()->attach($member, ['position' => 1]);
    Sanctum::actingAs($editor);

    outsidePost($this, 'work-orders', $workOrder->id, $member)
        ->assertCreated()->assertJsonPath('meta.enlisted_user_ids', []);

    Notification::assertSentTo($member, NoteMentionNotification::class, fn ($n) => ! str_contains($n->toArray($member)['message'], 'added you as'));
    expect(DB::table('work_order_participant')->where('work_order_id', $workOrder->id)->count())->toBe(1);
});

// AC-011
it('AC-011: editing a note enlists a new outsider; removing the mention or deleting the note keeps them', function () {
    Notification::fake();
    [$editor, $task] = outsideTask();
    $outsider = outsideMentionUser(['tasks.view']);
    Sanctum::actingAs($editor);

    $noteId = $this->postJson('/api/notes', ['entity_type' => 'tasks', 'entity_id' => $task->id, 'body' => 'Plain', 'mentions' => []])
        ->assertCreated()->json('data.id');

    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Hi '.outsideMentionSpan($outsider), 'mentions' => [$outsider->id]])
        ->assertOk()->assertJsonPath('meta.enlisted_user_ids', [$outsider->id]);
    Notification::assertSentToTimes($outsider, NoteMentionNotification::class, 1);

    $this->patchJson("/api/notes/{$noteId}", ['body' => 'No mention now', 'mentions' => []])
        ->assertOk()->assertJsonPath('meta.enlisted_user_ids', []);
    $this->deleteJson("/api/notes/{$noteId}")->assertOk();

    expect($task->watchers()->pluck('users.id')->all())->toBe([$outsider->id]);
});

// AC-012
it('AC-012: an inactive or service user cannot be mentioned outside the record', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    Sanctum::actingAs($editor);

    foreach ([['is_active' => false], ['is_service_account' => true]] as $attributes) {
        $user = outsideMentionUser(['work-orders.view'], $attributes);

        outsidePost($this, 'work-orders', $workOrder->id, $user)
            ->assertStatus(422)->assertJsonValidationErrors('mentions');
        expect(DB::table('work_order_participant')->where('user_id', $user->id)->exists())->toBeFalse();
    }
});

// AC-013
it('AC-013: an unsupported module still refuses an outsider', function () {
    $author = outsideMentionUser(['registries.view', 'registries.update', 'notes.create']);
    $outsider = outsideMentionUser();
    $registry = Registry::factory()->create();
    Sanctum::actingAs($author);

    outsidePost($this, 'registries', $registry->id, $outsider)
        ->assertStatus(422)->assertJsonValidationErrors('mentions');
    expect(Note::query()->count())->toBe(0);
});

it('a super-admin outsider counts as having module access', function () {
    [$editor, $workOrder] = outsideWorkOrder();
    $admin = outsideMentionUser();
    $admin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($editor);

    $items = collect($this->getJson("/api/notes/mentionable-users?entity_type=work-orders&entity_id={$workOrder->id}&scope=extended&ids[]={$admin->id}")
        ->assertOk()->json('items'))->keyBy('id');

    expect($items[$admin->id]['has_module_access'])->toBeTrue();
});
