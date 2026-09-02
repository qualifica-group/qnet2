<?php

use App\Models\Role;
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
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        // `viewAll` on top of the requested abilities: these suites predate
        // the membership scoping (user directive 2026-09-02) and none of them
        // is about it — the actor must see every commessa, as before. It
        // widens nothing on its own: every gate still needs its own base
        // ability, so the 403 assertions below keep their meaning. The
        // scoping itself is covered by WorkOrderVisibilityTest.
        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

it('403 without work-orders.viewAny', function () {
    $actor = workOrderUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/work-orders')->assertForbidden();
});

it('200: field catalogue is in the frozen data_contract order, status is absent (AC-052)', function () {
    $actor = workOrderUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/work-orders')
        ->assertOk()
        ->assertJsonPath('success', true);

    // Spec 0098 appends `attribute_values` at the end of the catalogue — the
    // requirement changed (a new field joined the resource), so this
    // expectation is updated to match, not tampered with.
    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['code', 'quote_id', 'title', 'type', 'start_date', 'supervisor_ids', 'participant_slots', 'callback_date', 'description', 'internal_notes', 'is_force_closed', 'force_close_reason', 'quote_line_ids', 'attribute_values'])
        ->and($keys)->not->toContain('status');

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['title']['mandatory'])->toBeTrue()
        ->and($fields['type']['mandatory'])->toBeTrue()
        // Spec 0096, D-6: NOT NULL columns, hence mandatory — but plainly
        // editable after create, unlike code/quote_id.
        ->and($fields['start_date']['mandatory'])->toBeTrue()
        ->and($fields['supervisor_ids']['mandatory'])->toBeTrue()
        ->and($fields['participant_slots']['mandatory'])->toBeFalse()
        ->and($fields['code']['mandatory'])->toBeFalse();
});

it('200: code/quote_id are editable only in create context, readonly once a model exists (AC-052)', function () {
    $actor = workOrderUserWith(['viewAny', 'create', 'view', 'update']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/work-orders')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.quote_id.editable', true);

    $target = WorkOrder::factory()->create();

    $this->getJson("/api/work-orders/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', false)
        ->assertJsonPath('permissions.fields.code.readonly', true)
        ->assertJsonPath('permissions.fields.quote_id.editable', false)
        ->assertJsonPath('permissions.fields.quote_id.readonly', true);
});

it('a restrictive DB row on the non-mandatory `description` field makes it readonly, and 422 if modified (AC-052)', function () {
    // `viewAll` lifts the membership scoping (user directive 2026-09-02):
    // this test is about the field-permission matrix, not about who may see
    // a commessa.
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $role = Role::create(['name' => 'work-order-description-locked']);
    $role->givePermissionTo(['work-orders.view', 'work-orders.update', 'work-orders.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'work-orders',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = WorkOrder::factory()->create(['description' => 'Original']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.description.editable', false);

    $this->patchJson("/api/work-orders/{$target->id}", ['description' => 'Changed'])
        ->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseHas('work_orders', ['id' => $target->id, 'description' => 'Original']);
});

it('a restrictive DB row on the mandatory `title` field is ignored (mandatory bypass), write succeeds (AC-052)', function () {
    // `viewAll` lifts the membership scoping (user directive 2026-09-02):
    // this test is about the field-permission matrix, not about who may see
    // a commessa.
    foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }

    $role = Role::create(['name' => 'work-order-title-locked']);
    $role->givePermissionTo(['work-orders.view', 'work-orders.update', 'work-orders.viewAll']);
    $role->fieldPermissions()->create([
        'resource' => 'work-orders',
        'field' => 'title',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = WorkOrder::factory()->create(['title' => 'Original']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.title.editable', true);

    $this->patchJson("/api/work-orders/{$target->id}", ['title' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Changed');
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = workOrderUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/work-orders')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
