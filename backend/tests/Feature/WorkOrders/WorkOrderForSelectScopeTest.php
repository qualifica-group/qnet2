<?php

use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| AC-056 — GET /api/work-orders/for-select is scoped by membership
|--------------------------------------------------------------------------
|
| The endpoint feeds the Task form's Commessa picker (spec 0101, T-04b) but
| the constraint it carries belongs to the Commessa module, which is why the
| file lives here rather than under tests/Feature/Tasks/.
|
| The scoping is a security boundary, not a nicety: without
| `WorkOrderVisibilityScope::scopeToActor()` the picker would disclose
| Commesse that the Commesse grid hides from the very same user. The scope
| IS applied today, in WorkOrderService::forSelectBaseQuery() — but nothing
| pinned it, so a rewrite of that one method would open a disclosure channel
| with the whole suite still green. That is the only reason this file
| exists, and it is why the grid and the picker are compared against each
| other below rather than asserted apart.
*/

if (! function_exists('workOrderPickerActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderPickerActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
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
 * @return array<int, int>
 */
function workOrderPickerIds(string $query = ''): array
{
    return collect(test()->getJson('/api/work-orders/for-select'.$query)->assertOk()->json('items'))
        ->pluck('id')->all();
}

// ---------------------------------------------------------------------------
// the constraint: the picker never shows what the grid hides
// ---------------------------------------------------------------------------

it('AC-056: the picker lists only the commesse the actor belongs to', function () {
    $actor = workOrderPickerActor(['view']);
    $supervised = WorkOrder::factory()->create(['title' => 'Da responsabile']);
    $supervised->supervisors()->attach($actor->id);
    $participated = WorkOrder::factory()->create(['title' => 'Da partecipante']);
    $participated->participants()->attach($actor->id, ['position' => 0]);
    $foreign = WorkOrder::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $ids = workOrderPickerIds();

    expect($ids)->toContain($supervised->id)
        ->toContain($participated->id)
        ->not->toContain($foreign->id);
});

it('AC-056: the picker lists every commessa for an actor holding work-orders.viewAll', function () {
    $actor = workOrderPickerActor(['view', 'viewAll']);
    $own = WorkOrder::factory()->create();
    $own->supervisors()->attach($actor->id);
    $foreign = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    expect(workOrderPickerIds())->toContain($own->id)->toContain($foreign->id);
});

it('AC-056: the picker lists every commessa for a super-admin', function () {
    workOrderPickerActor([]);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('super-admin'));
    $all = WorkOrder::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    // The EXACT set, not just "contains one of them": a `toContain` here
    // would pass on two commesse out of three, which is precisely the
    // partial-scope bug this case exists to rule out.
    expect(collect(workOrderPickerIds())->sort()->values()->all())
        ->toBe($all->pluck('id')->sort()->values()->all())
        ->and(WorkOrder::query()->count())->toBe(3);
});

it('AC-056: the picker and the Commesse grid agree on what the actor may see', function () {
    // The disclosure channel the constraint exists to close: the two
    // surfaces must never disagree, so they are compared in one test rather
    // than asserted apart.
    $actor = workOrderPickerActor(['viewAny', 'view']);
    $own = WorkOrder::factory()->create(['title' => 'Mia']);
    $own->supervisors()->attach($actor->id);
    WorkOrder::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $gridIds = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect(collect(workOrderPickerIds())->sort()->values()->all())->toBe($gridIds)
        ->and($gridIds)->toBe([$own->id]);
});

it('AC-056: ids[] hydrates an in-scope commessa and still hides an out-of-scope one', function () {
    // `ids[]` is edit-mode hydration and bypasses the search filter, but the
    // scope is a security boundary rather than a filter: asking for an id
    // directly must not lift it.
    //
    // Both halves in ONE call, because either alone is misleading: asserting
    // only the exclusion would pass even if `ids[]` were broken and returned
    // nothing at all. The control id proves the mechanism works while the
    // foreign id proves it stays bounded.
    $actor = workOrderPickerActor(['view']);
    $own = WorkOrder::factory()->create();
    $own->supervisors()->attach($actor->id);
    $foreign = WorkOrder::factory()->create();
    Sanctum::actingAs($actor);

    $ids = workOrderPickerIds("?ids[]={$own->id}&ids[]={$foreign->id}");

    expect($ids)->toContain($own->id)
        ->not->toContain($foreign->id);
});

it('AC-056: a non-member gets items: [] and pagination.total: 0, not the full list', function () {
    $actor = workOrderPickerActor(['view']);
    WorkOrder::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/work-orders/for-select')->assertOk();

    expect($response->json('items'))->toBe([])
        // The total must be scoped too: a correct page with an unscoped
        // count still discloses how many commesse exist.
        ->and($response->json('pagination.total'))->toBe(0)
        ->and(WorkOrder::query()->count())->toBe(3);
});

// ---------------------------------------------------------------------------
// envelope, auth and pagination bounds (ADR 0011)
// ---------------------------------------------------------------------------

it('AC-056: requires authentication (401)', function () {
    WorkOrder::factory()->create();

    $this->getJson('/api/work-orders/for-select')->assertUnauthorized();
});

it('AC-056: answers 200 without work-orders.viewAny and returns the for-select envelope', function () {
    $actor = workOrderPickerActor([]);
    $own = WorkOrder::factory()->create(['code' => 'COM-0001', 'title' => 'Prima commessa']);
    $own->supervisors()->attach($actor->id);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/work-orders/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);

    $item = collect($response->json('items'))->firstWhere('id', $own->id);

    expect($item['label'])->toContain('COM-0001')->toContain('Prima commessa');
});

it('AC-056: rejects a limit above 100 (422)', function () {
    $actor = workOrderPickerActor(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/work-orders/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

it('AC-056: rejects a negative offset (422)', function () {
    $actor = workOrderPickerActor(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/work-orders/for-select?offset=-1')
        ->assertStatus(422)->assertJsonValidationErrors('offset');
});

it('AC-056: search narrows within the scope, it never widens past it', function () {
    $actor = workOrderPickerActor(['view']);
    $own = WorkOrder::factory()->create(['title' => 'Ristrutturazione sede']);
    $own->supervisors()->attach($actor->id);
    $foreign = WorkOrder::factory()->create(['title' => 'Ristrutturazione altrui']);
    Sanctum::actingAs($actor);

    $ids = workOrderPickerIds('?search=Ristrutturazione');

    expect($ids)->toBe([$own->id])
        ->and($ids)->not->toContain($foreign->id);
});
