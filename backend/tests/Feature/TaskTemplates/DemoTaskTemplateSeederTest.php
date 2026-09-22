<?php

use App\Enums\TaskStatusGroup;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use Database\Seeders\DemoTaskTemplateSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ProductTypologySeeder;
use Database\Seeders\QualificaTaskTaxonomySeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| DemoTaskTemplateSeeder / DatabaseSeeder boundary (spec 0124, MT-B5)
|--------------------------------------------------------------------------
*/

/**
 * The status vocabulary DemoTaskTemplateSeeder resolves its open/pending
 * rows from (D-4), plus the demo account it now writes as (spec 0128:
 * TaskTemplateService::create() takes the actor it attributes rich text
 * attachments to — REQUIREMENT CHANGED, DemoUserSeeder joins the dependency
 * list DemoDataSeeder itself already runs ahead of DemoTaskTemplateSeeder).
 */
function seedTaskTemplateDependencies(): void
{
    test()->seed(RolePermissionSeeder::class);
    test()->seed(DemoUserSeeder::class);
    test()->seed(QualificaTaskTaxonomySeeder::class);
}

it('creates 3 realistic templates with their items, no duplicates', function (): void {
    seedTaskTemplateDependencies();

    test()->seed(DemoTaskTemplateSeeder::class);

    expect(TaskTemplate::count())->toBe(3);

    $names = TaskTemplate::query()->pluck('name')->all();
    expect($names)->toEqualCanonicalizing([
        'Avvio commessa standard',
        'Sopralluogo e progettazione',
        'Chiusura e collaudo',
    ]);

    expect(TaskTemplateItem::count())->toBeGreaterThanOrEqual(9)
        ->and(TaskTemplateItem::count())->toBeLessThanOrEqual(15);

    // Every item title is unique within its own template (no duplicate row
    // from a partial re-run) and sort_order is a dense 0..n-1 sequence.
    foreach (TaskTemplate::all() as $template) {
        $items = $template->items()->get();
        expect($items->pluck('title')->unique())->toHaveCount($items->count());
        expect($items->pluck('sort_order')->all())->toBe(range(0, $items->count() - 1));
    }
});

it('assigns due_offset_days and estimated_minutes, and mixes open/pending/unset statuses (D-4)', function (): void {
    seedTaskTemplateDependencies();

    test()->seed(DemoTaskTemplateSeeder::class);

    $items = TaskTemplateItem::all();

    expect($items->pluck('due_offset_days')->every(fn (int $days) => $days >= 0))->toBeTrue()
        ->and($items->pluck('estimated_minutes')->filter()->count())->toBeGreaterThan(0)
        ->and($items->pluck('task_status_id')->filter()->count())->toBeGreaterThan(0)
        ->and($items->pluck('task_status_id')->filter(fn ($id) => $id === null)->count())->toBeGreaterThan(0);

    // No item is assigned a status outside the open/pending phase (D-4).
    $assignedGroups = $items->pluck('taskStatus')->filter()->map(fn ($status) => $status->group)->unique();
    foreach ($assignedGroups as $group) {
        expect(in_array($group, [TaskStatusGroup::Open, TaskStatusGroup::Pending], true))->toBeTrue();
    }
});

it('re-running does not duplicate templates or items (idempotent)', function (): void {
    seedTaskTemplateDependencies();

    test()->seed(DemoTaskTemplateSeeder::class);
    $firstTemplateCount = TaskTemplate::count();
    $firstItemCount = TaskTemplateItem::count();
    $firstItemIds = TaskTemplateItem::query()->orderBy('id')->pluck('id')->all();

    test()->seed(DemoTaskTemplateSeeder::class);

    expect(TaskTemplate::count())->toBe($firstTemplateCount)
        ->and(TaskTemplateItem::count())->toBe($firstItemCount);

    // Re-running deletes and recreates: the item ids are NOT the same rows
    // (fresh primary keys), which is expected — only the shape is stable.
    $secondItemIds = TaskTemplateItem::query()->orderBy('id')->pluck('id')->all();
    expect($secondItemIds)->not->toBe($firstItemIds);
});

it('assigns stages (spec 0146, D-2) in dense sort_order, and leaves at least one item unstaged', function (): void {
    seedTaskTemplateDependencies();

    test()->seed(DemoTaskTemplateSeeder::class);

    expect(TaskTemplateStage::count())->toBeGreaterThan(0);

    foreach (TaskTemplate::all() as $template) {
        $stages = $template->stages()->get();

        if ($stages->isEmpty()) {
            continue;
        }

        expect($stages->pluck('sort_order')->all())->toBe(range(0, $stages->count() - 1));

        foreach ($stages as $stage) {
            expect($stage->items()->exists())->toBeTrue();
        }
    }

    expect(TaskTemplateItem::query()->whereNull('task_template_stage_id')->exists())->toBeTrue();
});

it('DatabaseSeeder alone creates no task templates', function (): void {
    // Mirrors DatabaseSeeder::run() minus `locations:add` (an unrelated geo
    // import, not exercisable against the in-memory sqlite test connection):
    // the clean seed path never calls DemoTaskTemplateSeeder.
    test()->seed(RolePermissionSeeder::class);
    test()->seed(UnitOfMeasureSeeder::class);
    test()->seed(ProductTypologySeeder::class);
    test()->seed(DemoUserSeeder::class);

    expect(TaskTemplate::count())->toBe(0)
        ->and(TaskTemplateItem::count())->toBe(0);
});
