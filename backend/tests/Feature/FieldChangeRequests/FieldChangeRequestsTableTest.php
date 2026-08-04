<?php

declare(strict_types=1);

use App\Models\FieldChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('fieldChangeRequestsActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function fieldChangeRequestsActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        return $user;
    }
}

// AC-036: 200 with the frozen column order + the three-state `status` filter,
// 403 without `field-change-requests.viewAny`.

it('GET /api/tables/field-change-requests/columns: 403 without viewAny, 200 with the frozen column order (AC-036)', function () {
    $actor = fieldChangeRequestsActorWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/field-change-requests/columns')->assertForbidden();

    $actor = fieldChangeRequestsActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/field-change-requests/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('field-change-requests');

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'id', 'resource_label', 'subject_label', 'field_label', 'current_label',
        'requested_label', 'reason', 'requested_by', 'created_at', 'status',
        'handled_by', 'handled_at', 'handling_note',
    ]);

    $status = collect($data['columns'])->firstWhere('id', 'status');
    expect($status['type'])->toBe('badge')
        ->and($status['options'])->toEqualCanonicalizing(['pending', 'approved', 'rejected']);
});

it('POST /api/tables/field-change-requests/rows: 403 without viewAny (AC-036)', function () {
    $actor = fieldChangeRequestsActorWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/field-change-requests/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertForbidden();
});

it('POST /api/tables/field-change-requests/rows: filters on `status` across the three states (AC-036)', function () {
    $actor = fieldChangeRequestsActorWith(['viewAny']);
    $pending = FieldChangeRequest::factory()->create();
    $approved = FieldChangeRequest::factory()->approved()->create();
    $rejected = FieldChangeRequest::factory()->rejected()->create();
    Sanctum::actingAs($actor);

    $byPending = $this->postJson('/api/tables/field-change-requests/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['pending']]],
    ])->assertOk();
    expect(collect($byPending->json('items'))->pluck('id')->all())->toBe([$pending->id]);

    $byApproved = $this->postJson('/api/tables/field-change-requests/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['approved']]],
    ])->assertOk();
    expect(collect($byApproved->json('items'))->pluck('id')->all())->toBe([$approved->id]);

    $byRejected = $this->postJson('/api/tables/field-change-requests/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['status' => ['filterType' => 'set', 'values' => ['rejected']]],
    ])->assertOk();
    expect(collect($byRejected->json('items'))->pluck('id')->all())->toBe([$rejected->id]);
});

it('POST /api/tables/field-change-requests/rows: projects resource_label/field_label/subject_label/requested_by (AC-036)', function () {
    $actor = fieldChangeRequestsActorWith(['viewAny']);
    $requester = User::factory()->create(['name' => 'Mario Rossi']);
    $request = FieldChangeRequest::factory()->create(['requested_by_id' => $requester->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/field-change-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $request->id);

    expect($row['resource_label'])->toBe('navigation.requestManagement')
        ->and($row['field_label'])->toBe('requestManagement.columns.source')
        ->and($row['subject_label'])->not->toBeNull()
        ->and($row['requested_by']['id'])->toBe($requester->id)
        ->and($row['requested_by']['name'])->toBe('Mario Rossi')
        ->and($row['handled_by'])->toBeNull();
});

// The grid's only row action: `view`, the entry point to the detail where
// Approve/Reject live (AC-047). Nothing on this table ever mutates a request.

it('GET /api/tables/field-change-requests/columns: advertises `view` only to who may read a request', function () {
    Sanctum::actingAs(fieldChangeRequestsActorWith(['viewAny', 'view']));

    $actions = $this->getJson('/api/tables/field-change-requests/columns')
        ->assertOk()
        ->json('data.actions');

    expect(collect($actions)->pluck('key')->all())->toBe(['view'])
        ->and($actions[0]['type'])->toBe('link');

    Sanctum::actingAs(fieldChangeRequestsActorWith(['viewAny']));

    expect($this->getJson('/api/tables/field-change-requests/columns')->assertOk()->json('data.actions'))
        ->toBe([]);
});

it('POST /api/tables/field-change-requests/rows: each row carries the `view` action, and only it', function () {
    $request = FieldChangeRequest::factory()->create();
    Sanctum::actingAs(fieldChangeRequestsActorWith(['viewAny', 'view']));

    $response = $this->postJson('/api/tables/field-change-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect(collect($response->json('items'))->firstWhere('id', $request->id)['actions'])->toBe(['view']);
});

it('POST /api/tables/field-change-requests/rows: no row action without `view` on the request (AC-039)', function () {
    $requester = User::factory()->create();
    $own = FieldChangeRequest::factory()->create(['requested_by_id' => $requester->id]);
    $other = FieldChangeRequest::factory()->create();

    $actor = fieldChangeRequestsActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    $rows = collect($this->postJson('/api/tables/field-change-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));
    expect($rows->firstWhere('id', $other->id)['actions'])->toBe([]);

    // Same actor, but requester of the row: the Policy grants `view` on it.
    $requester->givePermissionTo('field-change-requests.viewAny');
    Sanctum::actingAs($requester);
    $rows = collect($this->postJson('/api/tables/field-change-requests/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));
    expect($rows->firstWhere('id', $own->id)['actions'])->toBe(['view'])
        ->and($rows->firstWhere('id', $other->id)['actions'])->toBe([]);
});
