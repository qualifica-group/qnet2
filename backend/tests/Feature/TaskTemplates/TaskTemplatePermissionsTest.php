<?php

use App\Models\Role;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Authorization surface of the `task-templates` resource (spec 0124): base
// CRUD ability (AC-006) and the field-permission ceiling (AC-007).

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
// AC-006 — 403 on every gated endpoint without the matching permission;
// for-select 200 to any authenticated user regardless.
// ---------------------------------------------------------------------------

it('403 on show/store/update/destroy/table/export without the matching permission (AC-006)', function () {
    $template = TaskTemplate::factory()->create();
    Sanctum::actingAs(taskTemplateUserWith([]));

    $this->getJson("/api/task-templates/{$template->id}")->assertForbidden();
    $this->postJson('/api/task-templates', ['name' => 'X', 'items' => [['title' => 'A', 'due_offset_days' => 0]]])->assertForbidden();
    $this->patchJson("/api/task-templates/{$template->id}", ['name' => 'Y'])->assertForbidden();
    $this->deleteJson("/api/task-templates/{$template->id}")->assertForbidden();
    $this->getJson('/api/tables/task-templates/columns')->assertForbidden();
    $this->postJson('/api/exports/task-templates', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();
});

it('for-select: 200 without any task-templates permission (D-8, AC-006)', function () {
    Sanctum::actingAs(taskTemplateUserWith([]));

    $this->getJson('/api/task-templates/for-select')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-007 — DB field-permission matrix: an editable:false row rejects a
// CHANGED value with a field-keyed 422, no write.
// ---------------------------------------------------------------------------

it('update: description editable:false for the actor\'s role -> 422 "field not editable", no write (AC-007)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("task-templates.{$ability}");
    }

    $role = Role::create(['name' => 'task-template-locked']);
    $role->givePermissionTo(['task-templates.view', 'task-templates.update']);
    $role->fieldPermissions()->create([
        'resource' => 'task-templates',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $template = TaskTemplate::factory()->create(['description' => 'Originale']);
    TaskTemplateItem::factory()->forTemplate($template)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/task-templates/{$template->id}", ['description' => 'Nuova'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('description');

    expect($template->fresh()->description)->toBe('Originale');
});

it('update: a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = taskTemplateUserWith([]); // no task-templates.update at all
    $template = TaskTemplate::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/task-templates/{$template->id}", ['name' => 'Nope'])->assertForbidden();
});
