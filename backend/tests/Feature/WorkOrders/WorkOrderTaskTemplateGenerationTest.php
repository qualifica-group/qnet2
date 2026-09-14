<?php

use App\Enums\TaskStatusGroup;
use App\Models\Attachment;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskAssigned;
use App\Services\AttachmentService;
use App\Services\Tasks\TaskInitialStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Task generation on Commessa creation (spec 0124, D-2..D-9), AC-014..023.
 * `POST /api/work-orders` — the twin `POST /api/contracts/{contract}/
 * work-orders` path is covered separately by
 * ContractWorkOrderTaskTemplateTest (AC-018).
 */
uses(RefreshDatabase::class);

if (! function_exists('taskTemplateGenerationActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskTemplateGenerationActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

it('AC-014/AC-015: generates one task per row, assigned to every supervisor, dated off due_offset_days', function () {
    $quote = Quote::factory()->create();
    $supervisorA = User::factory()->create();
    $supervisorB = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create([
        'title' => 'Riga uno', 'description' => 'Desc uno', 'estimated_minutes' => 30, 'due_offset_days' => 0,
    ]);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create([
        'title' => 'Riga due', 'description' => null, 'estimated_minutes' => null, 'due_offset_days' => 5,
    ]);
    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Con modello', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisorA->id, $supervisorB->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    expect(WorkOrder::find($response->json('data.id'))->task_template_id)->toBe($template->id)
        ->and($response->json('data.task_template'))->toBe(['id' => $template->id, 'name' => $template->name]);

    $tasks = Task::query()->where('work_order_id', $response->json('data.id'))->orderBy('id')->get();
    expect($tasks)->toHaveCount(2);

    [$first, $second] = [$tasks[0], $tasks[1]];

    expect($first->title)->toBe('Riga uno')
        ->and($first->description)->toBe('Desc uno')
        ->and($first->estimated_minutes)->toBe(30)
        ->and($first->creator_id)->toBe($actor->id)
        ->and($first->requester_id)->toBe($actor->id)
        ->and($first->registry_id)->toBe($quote->opportunity->registry_id)
        ->and($first->opportunity_id)->toBe($quote->opportunity_id)
        ->and($first->start_date->toDateString())->toBe('2026-10-01')
        ->and($first->end_date->toDateString())->toBe('2026-10-01')
        ->and($first->requires_validation)->toBeFalse();

    expect($first->assignees()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$supervisorA->id, $supervisorB->id])->sort()->values()->all());

    // AC-015: due_offset_days 5 off the SAME start_date.
    expect($second->start_date->toDateString())->toBe('2026-10-01')
        ->and($second->end_date->toDateString())->toBe('2026-10-06');
});

it('AC-016: uses the row\'s own active open/pending status, falls back to the ordinary derivation otherwise', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();

    $pendingStatus = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->inStatus($pendingStatus)->create();

    // A row's status configured while valid, then deactivated in the
    // meantime (D-4): generation must fall back silently, no error.
    $staleStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->inStatus($staleStatus)->create();
    $staleStatus->update(['is_active' => false]);

    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Stati', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $tasks = Task::query()->where('work_order_id', $response->json('data.id'))->orderBy('id')->get();

    $fallbackStatusId = app(TaskInitialStatusResolver::class)->resolve([$supervisor->id], $actor->id, $actor->id);

    expect($tasks[0]->task_status_id)->toBe($pendingStatus->id)
        ->and($tasks[1]->task_status_id)->toBe($fallbackStatusId);
});

it('AC-017: the generated task carries an independent copy of the row attachment', function () {
    Storage::fake('local');
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();
    $sourceAttachment = $item->attach(UploadedFile::fake()->create('modello.pdf', 12, 'application/pdf'), 'documents');

    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Con allegato', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $task = Task::query()->where('work_order_id', $response->json('data.id'))->sole();
    $copy = $task->attachments()->sole();

    expect($copy->collection)->toBe('documents')
        ->and($copy->original_name)->toBe($sourceAttachment->original_name)
        ->and($copy->size)->toBe($sourceAttachment->size)
        ->and($copy->path)->not->toBe($sourceAttachment->path)
        ->and($copy->uploaded_by)->toBe($actor->id);
    Storage::disk('local')->assertExists($copy->path);

    // Deleting the model's own attachment leaves the task's copy intact.
    app(AttachmentService::class)->delete($sourceAttachment->fresh());
    Storage::disk('local')->assertMissing($sourceAttachment->path);
    Storage::disk('local')->assertExists($copy->path);
});

it('AC-019: an inactive or unknown task_template_id is 422 and creates nothing', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $inactiveTemplate = TaskTemplate::factory()->inactive()->create();
    $actor = taskTemplateGenerationActor(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Inattivo', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $inactiveTemplate->id,
    ])->assertStatus(422)->assertJsonValidationErrors('task_template_id');

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Ignoto', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('task_template_id');

    expect(WorkOrder::count())->toBe(0);
});

it('AC-019: a forced attachment-copy failure rolls back the whole commessa, no file left behind', function () {
    Storage::fake('local');
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();

    $goodItem = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create();
    $goodItem->attach(UploadedFile::fake()->create('ok.pdf', 4, 'application/pdf'), 'documents');

    $brokenItem = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create();
    $brokenAttachment = $brokenItem->attach(UploadedFile::fake()->create('broken.pdf', 4, 'application/pdf'), 'documents');
    // The row's own binary is missing when generation runs: AttachmentService
    // ::copyTo()'s Storage::copy() throws mid-way through the second item,
    // AFTER the first item's copy already succeeded and wrote a file.
    Storage::disk('local')->delete($brokenAttachment->path);

    $filesBefore = Storage::disk('local')->allFiles();
    $actor = taskTemplateGenerationActor(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Copia fallita', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertStatus(500);

    expect(WorkOrder::count())->toBe(0)
        ->and(Task::count())->toBe(0)
        ->and(Attachment::query()->where('collection', 'documents')->where('attachable_type', 'task')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toEqualCanonicalizing($filesBefore);
});

it('AC-020: POST without task_template_id creates no task and leaves the column null', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Senza modello', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
    ])->assertCreated();

    expect(WorkOrder::find($response->json('data.id'))->task_template_id)->toBeNull()
        ->and($response->json('data.task_template'))->toBeNull()
        ->and(Task::query()->where('work_order_id', $response->json('data.id'))->count())->toBe(0);
});

it('AC-021: the template and its generated tasks are independent snapshots in both directions', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create(['title' => 'Titolo originale']);
    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Snapshot', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $task = Task::query()->where('work_order_id', $response->json('data.id'))->sole();

    // Modello -> task: editing/deleting the row afterwards never touches
    // the already-generated task.
    $item->update(['title' => 'Titolo modificato']);
    expect($task->fresh()->title)->toBe('Titolo originale');

    $item->delete();
    expect($task->fresh())->not->toBeNull()
        ->and($task->fresh()->title)->toBe('Titolo originale');

    // Task -> modello: editing the generated task never touches the row it
    // came from (already deleted here, but the invariant is symmetrical:
    // there is no FK in either direction, D-6).
    $task->update(['title' => 'Task modificato']);
    expect(TaskTemplateItem::query()->find($item->id))->toBeNull();
});

it('AC-022: PATCH work-order with task_template_id is 422 (immutable after creation)', function () {
    $template = TaskTemplate::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $actor = taskTemplateGenerationActor(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['task_template_id' => $template->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_template_id');

    expect($workOrder->fresh()->task_template_id)->toBeNull();
});

it('AC-023: generation runs with work-orders.create alone (no tasks.create), supervisors are notified', function () {
    Notification::fake();
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->create();
    $actor = taskTemplateGenerationActor(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Notifiche', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    Notification::assertSentTo($supervisor, TaskAssigned::class);
});
