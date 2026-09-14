<?php

use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// PUT/PATCH /api/task-templates/{taskTemplate}: full sync of `items` (spec
// 0124, D-1, mirrors QuoteWorkflows\WorkflowStatusWriter::syncCustoms).
// AC-004, AC-005.

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

it('update: items = [C(id), A(id, renamed), new D] deletes B with its attachment, updates A, orders C,A,D (AC-004)', function () {
    Storage::fake('local');
    Sanctum::actingAs(taskTemplateUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $a = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'A']);
    $b = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'B']);
    $c = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)->create(['title' => 'C']);
    $b->attach(UploadedFile::fake()->create('b.pdf', 5, 'application/pdf'), 'documents');
    $attachmentId = $b->attachments()->first()->id;

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [
            ['id' => $c->id, 'title' => 'C', 'due_offset_days' => 0],
            ['id' => $a->id, 'title' => 'A rinominata', 'due_offset_days' => 1],
            ['title' => 'D', 'due_offset_days' => 3],
        ],
    ])->assertOk()
        ->assertJsonPath('data.items_count', 3)
        ->assertJsonPath('data.items.0.title', 'C')
        ->assertJsonPath('data.items.0.sort_order', 0)
        ->assertJsonPath('data.items.1.title', 'A rinominata')
        ->assertJsonPath('data.items.1.sort_order', 1)
        ->assertJsonPath('data.items.2.title', 'D')
        ->assertJsonPath('data.items.2.sort_order', 2);

    $this->assertDatabaseMissing('task_template_items', ['id' => $b->id]);
    $this->assertDatabaseMissing('attachments', ['id' => $attachmentId]);
    expect($a->fresh()->title)->toBe('A rinominata')
        ->and($c->fresh()->title)->toBe('C')
        ->and($template->items()->count())->toBe(3);
});

it('update: items.N.id belonging to another template -> 422 items.N.id, neither template changes (AC-005)', function () {
    Sanctum::actingAs(taskTemplateUserWith(['update']));

    $templateOne = TaskTemplate::factory()->create();
    $templateTwo = TaskTemplate::factory()->create();
    $itemOne = TaskTemplateItem::factory()->forTemplate($templateOne)->create(['title' => 'Own']);
    $foreignItem = TaskTemplateItem::factory()->forTemplate($templateTwo)->create(['title' => 'Foreign']);

    $this->patchJson("/api/task-templates/{$templateOne->id}", [
        'items' => [
            ['id' => $foreignItem->id, 'title' => 'Hijack', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.id');

    expect($itemOne->fresh()->title)->toBe('Own')
        ->and($foreignItem->fresh()->title)->toBe('Foreign')
        ->and($templateOne->items()->count())->toBe(1)
        ->and($templateTwo->items()->count())->toBe(1);
});

it('update: an unknown items.N.id (no matching row anywhere) -> 422 items.N.id, no change', function () {
    Sanctum::actingAs(taskTemplateUserWith(['update']));

    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create(['title' => 'Own']);

    $this->patchJson("/api/task-templates/{$template->id}", [
        'items' => [
            ['id' => 999999, 'title' => 'Ghost', 'due_offset_days' => 0],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.id');

    expect($item->fresh()->title)->toBe('Own');
});
