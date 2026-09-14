<?php

use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// GET /api/task-templates/for-select (spec 0124, D-9, ADR 0011). AC-009.

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

it('returns only active templates, ordered by name, with meta.items_count (AC-009)', function () {
    $active = TaskTemplate::factory()->create(['name' => 'Alfa', 'is_active' => true]);
    TaskTemplateItem::factory()->count(2)->forTemplate($active)->create();
    TaskTemplate::factory()->create(['name' => 'Zeta', 'is_active' => false]);
    Sanctum::actingAs(taskTemplateUserWith([]));

    $response = $this->getJson('/api/task-templates/for-select')->assertOk();

    $labels = collect($response->json('items'))->pluck('label')->all();
    expect($labels)->toBe(['Alfa']);
    expect($response->json('items.0.meta.items_count'))->toBe(2);
});

it('an inactive template is included when explicitly requested via ids[] (edit-mode hydration, AC-009)', function () {
    $inactive = TaskTemplate::factory()->create(['name' => 'Discontinuato', 'is_active' => false]);
    Sanctum::actingAs(taskTemplateUserWith([]));

    $response = $this->getJson("/api/task-templates/for-select?ids[]={$inactive->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids)->toContain($inactive->id);
});

it('subtitle carries the description', function () {
    TaskTemplate::factory()->create(['name' => 'Alfa', 'description' => 'Nota', 'is_active' => true]);
    Sanctum::actingAs(taskTemplateUserWith([]));

    $this->getJson('/api/task-templates/for-select')
        ->assertOk()
        ->assertJsonPath('items.0.subtitle', 'Nota');
});
