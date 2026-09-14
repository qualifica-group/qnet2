<?php

use App\Enums\TaskStatusGroup;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// DELETE /api/task-templates/{taskTemplate} (spec 0124, D-5) and the
// TaskStatusService::delete() guard it adds. AC-010, AC-011, AC-012.

if (! function_exists('taskTemplateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskTemplateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("task-templates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-templates.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-010 — an unused template: rows AND their files on disk are removed.
// ---------------------------------------------------------------------------

it('delete: 204 when unused, rows AND their attachments (files on disk too) are removed (AC-010)', function () {
    Storage::fake('local');
    Sanctum::actingAs(taskTemplateUserWith(['delete']));

    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();
    $attachment = $item->attach(UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf'), 'documents');
    $path = $attachment->path;

    $this->deleteJson("/api/task-templates/{$template->id}")->assertNoContent();

    $this->assertDatabaseMissing('task_templates', ['id' => $template->id]);
    $this->assertDatabaseMissing('task_template_items', ['id' => $item->id]);
    $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
    Storage::disk('local')->assertMissing($path);
});

// ---------------------------------------------------------------------------
// AC-011 — a template used by a Commessa: 409, template AND its generating
// Commessa's tasks (none touched here — nothing to generate) stay intact.
// ---------------------------------------------------------------------------

it('delete: 409 with an explanatory message when a Commessa was generated from it, the template stays (AC-011)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['delete']));
    $template = TaskTemplate::factory()->create();
    $workOrder = WorkOrder::factory()->create(['task_template_id' => $template->id]);

    $this->deleteJson("/api/task-templates/{$template->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', "Questo modello di task e' stato usato per generare commesse e non puo' essere eliminato. Disattivalo.");

    $this->assertDatabaseHas('task_templates', ['id' => $template->id]);
    $this->assertDatabaseHas('work_orders', ['id' => $workOrder->id, 'task_template_id' => $template->id]);
});

it('delete: the generic bulk-delete applies the same guard (AC-011)', function () {
    $actor = taskTemplateUserWith(['viewAny', 'delete']);
    $used = TaskTemplate::factory()->create();
    WorkOrder::factory()->create(['task_template_id' => $used->id]);
    $free = TaskTemplate::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/task-templates/bulk-delete', ['ids' => [$used->id, $free->id]]);

    $this->assertDatabaseHas('task_templates', ['id' => $used->id]);
    $this->assertDatabaseMissing('task_templates', ['id' => $free->id]);
});

it('403 without task-templates.delete', function () {
    $template = TaskTemplate::factory()->create();
    Sanctum::actingAs(taskTemplateUserWith([]));

    $this->deleteJson("/api/task-templates/{$template->id}")->assertForbidden();

    $this->assertDatabaseHas('task_templates', ['id' => $template->id]);
});

// ---------------------------------------------------------------------------
// AC-012 — TaskStatusService::delete(): a status used by a template row.
// ---------------------------------------------------------------------------

it('a task status used by a template row cannot be deleted (409, AC-012)', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("task-statuses.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('task-statuses.delete');
    Sanctum::actingAs($actor);

    $status = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->inStatus($status)->create();

    $this->deleteJson("/api/task-statuses/{$status->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This task status is used by a task template row and cannot be deleted.');

    $this->assertDatabaseHas('task_statuses', ['id' => $status->id]);
});

it('a task status NOT used by any template row deletes normally', function () {
    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        Permission::findOrCreate("task-statuses.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('task-statuses.delete');
    Sanctum::actingAs($actor);

    $status = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();

    $this->deleteJson("/api/task-statuses/{$status->id}")->assertNoContent();

    $this->assertDatabaseMissing('task_statuses', ['id' => $status->id]);
});
