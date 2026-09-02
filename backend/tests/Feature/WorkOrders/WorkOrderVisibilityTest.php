<?php

use App\Models\ExportRun;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Membership scoping of the Commessa (user directive 2026-09-02): only the
 * Responsabili (`supervisors`) and Partecipanti (`participants`) of a
 * commessa see it, unless the actor holds `work-orders.viewAll` or is
 * super-admin.
 *
 * Covers all four read/write surfaces the rule must hold on at once — the
 * SSRM grid (and everything derived from its baseQuery: export, bulk-delete,
 * distinct values), the detail endpoint, the write endpoints and the
 * aggregated activity log.
 */
uses(RefreshDatabase::class);

if (! function_exists('workOrderVisibilityActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderVisibilityActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

/**
 * @return array<int, string>
 */
function workOrderVisibleTitles(): array
{
    $items = test()->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

// ---------------------------------------------------------------------------
// grid — the actor lists only the commesse they belong to
// ---------------------------------------------------------------------------

it('lists only the commesse where the actor is Responsabile or Partecipante', function () {
    $actor = workOrderVisibilityActor(['viewAny', 'view']);

    WorkOrder::factory()->create(['title' => 'Da responsabile'])->supervisors()->attach($actor->id);
    WorkOrder::factory()->create(['title' => 'Da partecipante'])->participants()->attach($actor->id, ['position' => 0]);
    WorkOrder::factory()->create(['title' => 'Di altri'])->supervisors()->attach(User::factory()->create()->id);
    WorkOrder::factory()->create(['title' => 'Senza nessuno']);

    Sanctum::actingAs($actor);

    expect(workOrderVisibleTitles())->toBe(['Da partecipante', 'Da responsabile']);
});

it('lists every commessa with work-orders.viewAll', function () {
    $actor = workOrderVisibilityActor(['viewAny', 'view', 'viewAll']);

    WorkOrder::factory()->create(['title' => 'Mia'])->supervisors()->attach($actor->id);
    WorkOrder::factory()->create(['title' => 'Di altri']);

    Sanctum::actingAs($actor);

    expect(workOrderVisibleTitles())->toBe(['Di altri', 'Mia']);
});

it('lists every commessa for a super-admin, with no work-orders permission of their own', function () {
    workOrderVisibilityActor([]);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('super-admin'));

    WorkOrder::factory()->create(['title' => 'Di altri']);

    Sanctum::actingAs($actor);

    expect(workOrderVisibleTitles())->toBe(['Di altri']);
});

it('exports only the visible commesse: the export derives from the same scoped baseQuery', function () {
    Storage::fake('local');
    $actor = workOrderVisibilityActor(['viewAny', 'view', 'export']);

    WorkOrder::factory()->create(['title' => 'Mia'])->participants()->attach($actor->id, ['position' => 0]);
    WorkOrder::factory()->create(['title' => 'Di altri']);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/work-orders', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();

    expect($run->row_count)->toBe(1);

    $csv = Storage::disk('local')->get($run->file_path);

    expect($csv)->toContain('Mia')->not->toContain('Di altri');
});

it('bulk-delete cannot reach a commessa the actor does not belong to', function () {
    $actor = workOrderVisibilityActor(['viewAny', 'view', 'delete']);
    $foreign = WorkOrder::factory()->create();

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/work-orders/bulk-delete', ['ids' => [$foreign->id]]);

    $this->assertDatabaseHas('work_orders', ['id' => $foreign->id]);
});

// ---------------------------------------------------------------------------
// detail, writes and activity log — the per-record gate
// ---------------------------------------------------------------------------

it('GET show: 200 as Responsabile, 200 as Partecipante, 403 otherwise', function () {
    $actor = workOrderVisibilityActor(['view']);
    $supervised = WorkOrder::factory()->create();
    $supervised->supervisors()->attach($actor->id);
    $participated = WorkOrder::factory()->create();
    $participated->participants()->attach($actor->id, ['position' => 0]);
    $foreign = WorkOrder::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$supervised->id}")->assertOk();
    $this->getJson("/api/work-orders/{$participated->id}")->assertOk();
    $this->getJson("/api/work-orders/{$foreign->id}")->assertForbidden();
});

it('GET show: 200 on any commessa with work-orders.viewAll', function () {
    $actor = workOrderVisibilityActor(['view', 'viewAll']);
    $foreign = WorkOrder::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$foreign->id}")->assertOk();
});

it('PATCH update: 403 on a commessa the actor does not belong to, even with work-orders.update', function () {
    $actor = workOrderVisibilityActor(['view', 'update']);
    $foreign = WorkOrder::factory()->create(['title' => 'Intatta']);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$foreign->id}", ['title' => 'Modificata'])->assertForbidden();

    $this->assertDatabaseHas('work_orders', ['id' => $foreign->id, 'title' => 'Intatta']);
});

it('DELETE: 403 on a commessa the actor does not belong to, even with work-orders.delete', function () {
    $actor = workOrderVisibilityActor(['view', 'delete']);
    $foreign = WorkOrder::factory()->create();

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$foreign->id}")->assertForbidden();

    $this->assertDatabaseHas('work_orders', ['id' => $foreign->id]);
});

it('the actor keeps writing the commesse they are Responsabile of', function () {
    $actor = workOrderVisibilityActor(['view', 'update']);
    $own = WorkOrder::factory()->create(['title' => 'Prima']);
    $own->supervisors()->attach($actor->id);

    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$own->id}", ['title' => 'Dopo'])->assertOk();

    $this->assertDatabaseHas('work_orders', ['id' => $own->id, 'title' => 'Dopo']);
});

it('activity log: 403 on a commessa the actor does not belong to, 200 on their own', function () {
    $actor = workOrderVisibilityActor(['view', 'viewActivity']);
    $own = WorkOrder::factory()->create();
    $own->participants()->attach($actor->id, ['position' => 0]);
    $foreign = WorkOrder::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/work-orders/{$own->id}")->assertOk();
    $this->getJson("/api/activity-log/work-orders/{$foreign->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// the rule narrows, it never widens
// ---------------------------------------------------------------------------

it('being a Responsabile does not replace the work-orders.view permission', function () {
    $actor = workOrderVisibilityActor([]);
    $own = WorkOrder::factory()->create();
    $own->supervisors()->attach($actor->id);

    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$own->id}")->assertForbidden();
});
