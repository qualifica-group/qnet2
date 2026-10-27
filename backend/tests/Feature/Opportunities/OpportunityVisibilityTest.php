<?php

use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\OpportunityVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Spec 0316 (MT-02): record visibility of opportunities. view = "mine"
 * (Supervisore or Gestore account), viewAll lifts it, neither grants access
 * on its own. Existing grid/CRUD tests now grant viewAll in setup
 * (REQUIREMENT CHANGE per D-1).
 */
uses(RefreshDatabase::class);

if (! function_exists('visibilityActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function visibilityActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'export', 'viewAll'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('visibilityFixture')) {
    /**
     * @return array{supervised: Opportunity, managed: Opportunity, foreign: Opportunity}
     */
    function visibilityFixture(User $actor): array
    {
        $supervised = Opportunity::factory()->create(['supervisor_id' => $actor->id, 'name' => 'Supervised']);
        $managed = Opportunity::factory()->create(['name' => 'Managed']);
        $managed->managers()->attach($actor->id, ['position' => 3]);
        $foreign = Opportunity::factory()->create(['name' => 'Foreign']);

        return compact('supervised', 'managed', 'foreign');
    }
}

function visibilityRowIds($test): array
{
    return collect($test->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();
}

it('rows: view alone lists only supervised or managed opportunities, total matches (AC-001)', function () {
    $actor = visibilityActor(['viewAny', 'view']);
    $f = visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 50])->assertOk();

    expect(collect($response->json('items'))->pluck('id')->sort()->values()->all())
        ->toBe(collect([$f['supervised']->id, $f['managed']->id])->sort()->values()->all())
        ->and($response->json('pagination.total'))->toBe(2);
});

it('show: 403 on a foreign opportunity with view only, 200 with viewAll (AC-002)', function () {
    $actor = visibilityActor(['viewAny', 'view']);
    $f = visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunities/{$f['foreign']->id}")->assertForbidden();
    $this->getJson("/api/opportunities/{$f['managed']->id}")->assertOk();

    $all = visibilityActor(['viewAny', 'view', 'viewAll']);
    Sanctum::actingAs($all);

    $this->getJson("/api/opportunities/{$f['foreign']->id}")->assertOk();
    expect(visibilityRowIds($this))->toHaveCount(3);
});

it('viewAll without view sees nothing: levels narrow, never grant (AC-003)', function () {
    $actor = visibilityActor(['viewAll']);
    $f = visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 50])->assertForbidden();
    $this->getJson("/api/opportunities/{$f['supervised']->id}")->assertForbidden();
});

it('update and delete: a foreign record is refused (403/404) and unchanged; view alone cannot PUT an own one (AC-004)', function () {
    $actor = visibilityActor(['viewAny', 'view', 'update', 'delete']);
    $f = visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $this->putJson("/api/opportunities/{$f['foreign']->id}", ['estimated_value' => 999])->assertForbidden();
    $this->deleteJson("/api/opportunities/{$f['foreign']->id}")->assertForbidden();
    $this->patchJson("/api/tables/opportunities/rows/{$f['foreign']->id}", ['column' => 'estimated_value', 'value' => 999])
        ->assertNotFound();
    expect($f['foreign']->fresh())->not->toBeNull();
    expect($f['foreign']->fresh()->estimated_value)->not->toEqual(999);

    $viewer = visibilityActor(['viewAny', 'view']);
    $own = Opportunity::factory()->create(['supervisor_id' => $viewer->id]);
    Sanctum::actingAs($viewer);

    $this->putJson("/api/opportunities/{$own->id}", ['estimated_value' => 5])->assertForbidden();
});

it('for-select: only visible opportunities, also through ids[] (AC-005)', function () {
    $actor = visibilityActor(['viewAny', 'view']);
    $f = visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson("/api/opportunities/for-select?ids[]={$f['foreign']->id}&ids[]={$f['managed']->id}")
        ->assertOk()->json('items'))->pluck('id');

    expect($ids->all())->toContain($f['supervised']->id, $f['managed']->id)
        ->not->toContain($f['foreign']->id);
});

it('filter values contain only visible records (AC-006)', function () {
    $actor = visibilityActor(['viewAny', 'view']);
    visibilityFixture($actor);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/opportunities/values', ['columnId' => 'name'])
        ->assertOk()->json('data.values');

    expect($values)->toContain('Supervised', 'Managed')->not->toContain('Foreign');
});

it('stats: widgets are computed on visible records, per-user cache (AC-007)', function () {
    $a = visibilityActor(['viewAny', 'view']);
    Opportunity::factory()->count(2)->create(['supervisor_id' => $a->id]);
    $b = visibilityActor(['viewAny', 'view']);
    Opportunity::factory()->create(['supervisor_id' => $b->id]);
    Opportunity::factory()->create();

    $total = function (User $actor) {
        Sanctum::actingAs($actor);

        return collect($this->getJson('/api/stats/opportunities')->assertOk()->json('data.widgets'))
            ->keyBy('key')['total']['value'];
    };

    expect($total($a))->toBe(2)->and($total($b))->toBe(1);
});

it('scope is fail-closed for a null actor and super-admin sees everything (AC-042)', function () {
    Opportunity::factory()->count(2)->create();

    expect(OpportunityVisibilityScope::scopeToActor(Opportunity::query(), null)->count())->toBe(0);

    Role::findOrCreate('super-admin');
    $admin = User::factory()->create()->assignRole('super-admin');
    $record = Opportunity::query()->first();

    expect(OpportunityVisibilityScope::scopeToActor(Opportunity::query(), $admin)->count())->toBe(2)
        ->and(OpportunityVisibilityScope::isVisibleTo($admin, $record))->toBeTrue();
});
