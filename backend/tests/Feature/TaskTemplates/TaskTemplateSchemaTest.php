<?php

use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\WorkOrder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Schema and relations of `task_templates`/`task_template_items` and their
| two FKs into the rest of the schema (spec 0124, MT-B1)
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------------------------
// Columns
// ---------------------------------------------------------------------------

it('task_templates carries every column of the data_contract', function () {
    $expected = ['id', 'name', 'description', 'is_active', 'created_at', 'updated_at'];

    expect(Schema::getColumnListing('task_templates'))->toEqualCanonicalizing($expected);
});

it('task_template_items carries every column of the data_contract', function () {
    $expected = [
        'id', 'task_template_id', 'title', 'description', 'estimated_minutes',
        'task_status_id', 'due_offset_days', 'sort_order', 'created_at', 'updated_at',
        // spec 0146, D-2: the "Fase" this row sits in, null for "Senza fase".
        'task_template_stage_id',
    ];

    expect(Schema::getColumnListing('task_template_items'))->toEqualCanonicalizing($expected);
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('TaskTemplate::items() returns the rows ordered by sort_order, regardless of insertion order', function () {
    $template = TaskTemplate::factory()->create();
    $c = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)->create(['title' => 'C']);
    $a = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'A']);
    $b = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'B']);

    expect($template->items()->pluck('id')->all())->toBe([$a->id, $b->id, $c->id]);
});

it('TaskTemplateItem::template() resolves the owning header via task_template_id', function () {
    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();

    expect($item->template()->first()->is($template))->toBeTrue();
});

it('TaskTemplateItem::taskStatus() resolves the optional initial status', function () {
    $status = TaskStatus::factory()->create();
    $item = TaskTemplateItem::factory()->create(['task_status_id' => $status->id]);

    expect($item->taskStatus()->first()->is($status))->toBeTrue();
});

it('TaskTemplate::workOrders() returns the commesse generated from this template', function () {
    $template = TaskTemplate::factory()->create();
    $workOrder = WorkOrder::factory()->create(['task_template_id' => $template->id]);
    WorkOrder::factory()->create(); // unrelated, must not appear

    expect($template->workOrders()->pluck('id')->all())->toBe([$workOrder->id]);
});

it('WorkOrder::taskTemplate() resolves the model a commessa was generated from', function () {
    $template = TaskTemplate::factory()->create();
    $workOrder = WorkOrder::factory()->create(['task_template_id' => $template->id]);

    expect($workOrder->taskTemplate()->first()->is($template))->toBeTrue();
});

it('TaskStatus::taskTemplateItems() returns the rows currently pointing at this status', function () {
    $status = TaskStatus::factory()->create();
    $item = TaskTemplateItem::factory()->create(['task_status_id' => $status->id]);
    TaskTemplateItem::factory()->create(); // unrelated (null status), must not appear

    expect($status->taskTemplateItems()->pluck('id')->all())->toBe([$item->id]);
});

// ---------------------------------------------------------------------------
// FK behaviour
// ---------------------------------------------------------------------------

it('deleting a template cascades its items at the database level (cascadeOnDelete)', function () {
    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();

    $template->delete();

    $this->assertDatabaseMissing('task_template_items', ['id' => $item->id]);
});

// The attachments of a cascaded-away item are NOT swept by this DB-level FK
// (a schema cascadeOnDelete removes rows directly, bypassing Eloquent's
// `deleting` event — HasAttachments' own cleanup hook never fires for it).
// AC-010's full "rows AND files on disk removed" contract is therefore
// TaskTemplateService::delete()'s job (MT-B2), which must loop the items and
// delete each one through Eloquent before the header, not rely on the FK.
it('the item attachment cleanup hook does NOT fire on a DB-level cascade (HasAttachments only cleans up on an Eloquent ::delete())', function () {
    Storage::fake('local');

    $template = TaskTemplate::factory()->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create();
    $item->attach(
        UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        'documents',
    );
    $attachmentId = $item->attachments()->first()->id;

    $template->delete();

    $this->assertDatabaseMissing('task_template_items', ['id' => $item->id]);
    $this->assertDatabaseHas('attachments', ['id' => $attachmentId]);
});

it('a template referenced by a work order cannot be dropped at DB level (restrictOnDelete, D-5)', function () {
    $template = TaskTemplate::factory()->create();
    WorkOrder::factory()->create(['task_template_id' => $template->id]);

    expect(fn () => DB::table('task_templates')->where('id', $template->id)->delete())
        ->toThrow(QueryException::class);
});

it('a task status referenced by a template item cannot be dropped at DB level (restrictOnDelete, AC-012)', function () {
    $status = TaskStatus::factory()->create();
    TaskTemplateItem::factory()->create(['task_status_id' => $status->id]);

    expect(fn () => DB::table('task_statuses')->where('id', $status->id)->delete())
        ->toThrow(QueryException::class);
});

it('a work order without a template keeps task_template_id null', function () {
    $workOrder = WorkOrder::factory()->create();

    expect($workOrder->task_template_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// Migrations are reversible
// ---------------------------------------------------------------------------

it('the three migrations roll back and re-apply cleanly, in dependency order', function () {
    $addToWorkOrders = require database_path('migrations/2026_09_15_100200_add_task_template_id_to_work_orders_table.php');
    $items = require database_path('migrations/2026_09_15_100100_create_task_template_items_table.php');
    $templates = require database_path('migrations/2026_09_15_100000_create_task_templates_table.php');

    $addToWorkOrders->down();
    $items->down();
    $templates->down();

    expect(Schema::hasColumn('work_orders', 'task_template_id'))->toBeFalse()
        ->and(Schema::hasTable('task_template_items'))->toBeFalse()
        ->and(Schema::hasTable('task_templates'))->toBeFalse();

    $templates->up();
    $items->up();
    $addToWorkOrders->up();

    expect(Schema::hasColumn('work_orders', 'task_template_id'))->toBeTrue()
        ->and(Schema::hasTable('task_template_items'))->toBeTrue()
        ->and(Schema::hasTable('task_templates'))->toBeTrue()
        ->and(DB::table('task_templates')->count())->toBe(0);
});
