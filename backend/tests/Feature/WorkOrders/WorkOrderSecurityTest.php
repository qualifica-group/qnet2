<?php

use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-050 — 403/401 on every endpoint without the matching permission
// ---------------------------------------------------------------------------

it('GET tables/work-orders/rows: 403 without work-orders.viewAny', function () {
    $actor = workOrderUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('GET show: 403 without work-orders.view (AC-050)', function () {
    $actor = workOrderUserWith([]);
    $target = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$target->id}")->assertForbidden();
});

it('POST store: 403 without work-orders.create, no row created (AC-050)', function () {
    $actor = workOrderUserWith([]);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $countBefore = WorkOrder::count();

    $this->postJson('/api/work-orders', ['quote_id' => $quote->id, 'title' => 'Nope', 'type' => 'processing'])->assertForbidden();

    expect(WorkOrder::count())->toBe($countBefore);
});

it('PATCH update: 403 without work-orders.update, no change persisted (AC-050)', function () {
    $actor = workOrderUserWith([]);
    $target = WorkOrder::factory()->create(['title' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$target->id}", ['title' => 'Changed'])->assertForbidden();

    $this->assertDatabaseHas('work_orders', ['id' => $target->id, 'title' => 'Untouched']);
});

it('DELETE destroy: 403 without work-orders.delete, row NOT removed (AC-050)', function () {
    $actor = workOrderUserWith([]);
    $target = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/work-orders/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('work_orders', ['id' => $target->id]);
});

it('every work-orders endpoint requires authentication (401)', function () {
    $target = WorkOrder::factory()->create();

    $this->getJson('/api/work-orders/next-code')->assertUnauthorized();
    $this->getJson("/api/work-orders/{$target->id}")->assertUnauthorized();
    $this->postJson('/api/work-orders', [])->assertUnauthorized();
    $this->patchJson("/api/work-orders/{$target->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/work-orders/{$target->id}")->assertUnauthorized();
    $this->postJson('/api/tables/work-orders/rows', [])->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// AC-051 — permissions:sync creates exactly the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 work-orders.* permissions, derived from the Policy alone (AC-051)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "work-orders.{$ability}")->exists())->toBeTrue();
    }

    expect(Permission::where('name', 'like', 'work-orders.%')->count())->toBe(8);
});

// ---------------------------------------------------------------------------
// AC-053 — navigation node gated by work-orders.view
// ---------------------------------------------------------------------------

it('navigation: the work-orders node only shows with work-orders.view (AC-053)', function () {
    Permission::findOrCreate('work-orders.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('work-orders');

    $withView = User::factory()->create();
    $withView->givePermissionTo('work-orders.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('work-orders');
});
