<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0097 — the work panel writes the Offerta's WHOLE team (`manager_slots`)
 * instead of the lone GA2 "Operatore" (`operator_id`), and the field-permission
 * key moves with it. AC-004 -> AC-009, plus the creation default (AC-002) on
 * the one shape the existing create tests do not cover: a team submitted with
 * its OPERATOR slot left empty.
 */
uses(RefreshDatabase::class);

if (! function_exists('teamSlotsActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management ability names
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function teamSlotsActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'team-slots-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('teamSlotsQuote')) {
    /**
     * An Offerta whose own `quote_user` team is $slots (position => user id),
     * with `quotes.operator_id` mirroring the OPERATOR slot exactly as
     * QuoteManagerWriter keeps it (spec 0087, INV-2).
     *
     * A decoy Opportunity shifts the two id sequences apart, so an assertion
     * mixing up quote and opportunity ids can never pass by coincidence.
     *
     * @param  array<int, int>  $slots
     */
    function teamSlotsQuote(array $slots): Quote
    {
        Opportunity::factory()->create();

        $quote = Quote::factory()->create([
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
            $quote->opportunity->managers()->attach($userId, ['position' => $position]);
        }

        return $quote;
    }
}

if (! function_exists('teamSlotsCreatePayload')) {
    /**
     * The smallest valid POST body (registry branch + one product line + the
     * mandatory Fonte) — local to this file rather than borrowed from another
     * test's global helper, so running this file alone still resolves.
     *
     * @return array<string, mixed>
     */
    function teamSlotsCreatePayload(): array
    {
        $businessFunction = BusinessFunction::factory()->create();

        return [
            'registry_id' => Registry::factory()->create()->id,
            'source_id' => Source::factory()->create()->id,
            'product_lines' => [[
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]],
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-008 — the two write vocabularies are mutually exclusive
// ---------------------------------------------------------------------------

it('AC-008: manager_slots and operator_id in the same PATCH are rejected (422)', function () {
    $actor = teamSlotsActor();
    $quote = teamSlotsQuote([]);
    $operator = User::factory()->create();
    Sanctum::actingAs($actor);

    // The error is reported on `operator_id`: that is the key this endpoint
    // refuses outright (lead decision 2026-09-02, `prohibited`), which covers
    // the both-keys collision and the operator_id-alone case with one rule.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $operator->id],
        'operator_id' => $operator->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operator_id');

    expect($quote->fresh()->operator_id)->toBeNull()
        ->and($quote->managers()->count())->toBe(0);
});

it('AC-008: operator_id ALONE on the panel PATCH is rejected, never a silent 200', function () {
    $actor = teamSlotsActor();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    // Spec 0086 mt06's lesson: a key this endpoint no longer writes must
    // FAIL, not be validated, stripped by the controller and answered 200
    // with nothing written.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'operator_id' => $newOperator->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('operator_id');

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

// ---------------------------------------------------------------------------
// AC-004 — the gate moved onto the new key, and it is CHANGE-based (spec 0008)
// ---------------------------------------------------------------------------

it('AC-004: a role with manager_slots non-editable gets a 422 on a real team change', function () {
    $actor = teamSlotsActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'manager_slots', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $newOperator->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('AC-004: resubmitting the CURRENT team on a non-editable manager_slots is a no-op, not a 422', function () {
    $actor = teamSlotsActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'manager_slots', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    // The very payload the panel re-submits untouched, padded with the empty
    // trailing slots the form renders (spec 0008: only a real CHANGE is 422).
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$first->id, $operator->id, null, null],
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('AC-004: a permuted team on a non-editable manager_slots is a real change, so it is rejected', function () {
    $actor = teamSlotsActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'manager_slots', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    // Same two members, swapped slots: it MOVES the operator, so it can never
    // read as "nothing changed" (the shared field-permission comparison
    // normalizes lists order-insensitively — UpdateRequestRequest overrides it
    // for this key precisely to close that hole).
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$operator->id, $first->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('AC-004: manager_slots not visible collapses to non-editable in the panel envelope', function () {
    $actor = teamSlotsActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'manager_slots', 'visible' => false, 'editable' => false, 'required' => false],
    );
    $quote = teamSlotsQuote([]);
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('permissions.fields');

    expect($fields['manager_slots']['visible'])->toBeFalse()
        ->and($fields['manager_slots']['hidden'])->toBeTrue()
        ->and($fields['manager_slots']['editable'])->toBeFalse()
        ->and($fields)->not->toHaveKey('operator_id');
});

// ---------------------------------------------------------------------------
// AC-007 / D-6 — the notification and the log stay anchored on the OPERATOR
// ---------------------------------------------------------------------------

it('AC-007: moving the OPERATOR slot notifies the new operator and logs both keys', function () {
    Notification::fake();

    $actor = teamSlotsActor();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $newOperator->id],
    ])->assertOk();

    Notification::assertSentTo($newOperator, RecordAssignmentNotification::class);
    Notification::assertNotSentTo($operator, RecordAssignmentNotification::class);

    $properties = Activity::query()
        ->where('subject_type', $quote->opportunity->getMorphClass())
        ->where('subject_id', $quote->opportunity_id)
        ->where('event', 'updated')
        ->sole()
        ->properties;

    expect($properties->get('attributes'))->toMatchArray([
        'manager_slots' => [null, $newOperator->id],
        'operator_id' => $newOperator->id,
    ])->and($properties->get('old'))->toMatchArray([
        'manager_slots' => [null, $operator->id],
        'operator_id' => $operator->id,
    ]);
});

it('AC-007: changing only the OTHER slots notifies nobody and leaves the operator in place', function () {
    Notification::fake();

    $actor = teamSlotsActor();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([ManagerPositions::OPERATOR => $operator->id]);
    $teammate = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$teammate->id, $operator->id],
    ])->assertOk();

    Notification::assertNothingSent();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $teammate->id,
        'position' => 1,
    ]);

    $properties = Activity::query()
        ->where('subject_id', $quote->opportunity_id)
        ->where('event', 'updated')
        ->sole()
        ->properties;

    expect($properties->get('attributes'))->toHaveKey('manager_slots')
        ->and($properties->get('attributes'))->not->toHaveKey('operator_id');
});

it('AC-007: a team submission identical to the persisted one writes nothing at all, trailing empty slots included', function () {
    Notification::fake();

    $actor = teamSlotsActor();
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    // The exact wire shape the panel sends: the form always renders (and
    // submits) its default number of cards, so the payload carries trailing
    // nulls beyond the filled positions. They describe empty slots, not a
    // change — position-for-position this IS the persisted team.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$first->id, $operator->id, null, null],
    ])->assertOk();

    Notification::assertNothingSent();

    expect(Activity::query()->where('subject_id', $quote->opportunity_id)->where('event', 'updated')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005 — the grid cell keeps writing the single OPERATOR slot, gated by the
// team's key
// ---------------------------------------------------------------------------

it('AC-005: the operator_ga2 cell still writes ONLY the OPERATOR slot', function () {
    $actor = teamSlotsActor();
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $newOperator->id,
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($newOperator->id);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $newOperator->id,
        'position' => ManagerPositions::OPERATOR,
    ]);
    // The rest of the team is untouched: the cell edits one slot, not the team.
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $first->id,
        'position' => 1,
    ]);
});

it('AC-005: the operator_ga2 cell is gated by the manager_slots permission', function () {
    $actor = teamSlotsActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'manager_slots', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['operator_ga2']['editable'])->toBeFalse();

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => User::factory()->create()->id,
    ])->assertForbidden();

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

// ---------------------------------------------------------------------------
// AC-009 — the single-slot channels are untouched by the new editor
// ---------------------------------------------------------------------------

it('AC-009: the bulk assign moves the OPERATOR slot and leaves the rest of the team in place', function () {
    $actor = teamSlotsActor(['viewAny', 'view', 'update', 'viewAll', 'assignOperator']);
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = teamSlotsQuote([1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'mode' => 'single',
        'operator_id' => $newOperator->id,
    ])->assertOk();

    expect($quote->fresh()->operator_id)->toBe($newOperator->id);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $first->id,
        'position' => 1,
    ]);
});

// ---------------------------------------------------------------------------
// AC-002 — the creation default reaches the OPERATOR slot only
// ---------------------------------------------------------------------------

it('AC-002: a submitted team with an empty OPERATOR slot receives the creating actor there', function () {
    $actor = teamSlotsActor(['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator']);
    $teammate = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        ...teamSlotsCreatePayload(),
        'manager_slots' => [$teammate->id, null],
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));

    expect($quote->operator_id)->toBe($actor->id);
    // The creation response is the panel's own shape: the team it just wrote
    // must come back on it, or the form reloads on an invisible team.
    $response->assertJsonPath('data.managers', [
        ['id' => $teammate->id, 'name' => $teammate->name, 'position' => 1],
        ['id' => $actor->id, 'name' => $actor->name, 'position' => ManagerPositions::OPERATOR],
    ]);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $teammate->id,
        'position' => 1,
    ]);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $actor->id,
        'position' => ManagerPositions::OPERATOR,
    ]);
});

it('AC-002: the creating actor submitted in another slot is MOVED to the OPERATOR slot, never duplicated', function () {
    $actor = teamSlotsActor(['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        ...teamSlotsCreatePayload(),
        'manager_slots' => [$actor->id, null],
    ])->assertCreated();

    $quote = Quote::findOrFail($response->json('data.id'));

    expect($quote->operator_id)->toBe($actor->id)
        ->and($quote->managers()->count())->toBe(1);
    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $actor->id,
        'position' => ManagerPositions::OPERATOR,
    ]);
});

// ---------------------------------------------------------------------------
// AC-006 — the already configured matrix rows survive the key rename
// ---------------------------------------------------------------------------

it('AC-006: the migration renames the configured operator_id rows to manager_slots, and back', function () {
    $role = Role::create(['name' => 'matrix-rename-role-'.uniqid()]);
    $migration = require database_path('migrations/2026_09_02_230000_rename_request_management_operator_field_permission.php');

    $row = ['role_id' => $role->id, 'resource' => 'request-management', 'field' => 'operator_id', 'visible' => true, 'editable' => false, 'required' => false];
    DB::table('role_field_permissions')->insert($row);

    $migration->up();

    $renamed = DB::table('role_field_permissions')->where('role_id', $role->id)->sole();
    expect($renamed->field)->toBe('manager_slots')
        ->and((bool) $renamed->editable)->toBeFalse();

    $migration->down();

    expect(DB::table('role_field_permissions')->where('role_id', $role->id)->sole()->field)->toBe('operator_id');
});

it('AC-006: a pre-existing manager_slots row does not break the rename, the configured one wins', function () {
    $role = Role::create(['name' => 'matrix-conflict-role-'.uniqid()]);
    $migration = require database_path('migrations/2026_09_02_230000_rename_request_management_operator_field_permission.php');

    DB::table('role_field_permissions')->insert([
        ['role_id' => $role->id, 'resource' => 'request-management', 'field' => 'operator_id', 'visible' => true, 'editable' => false, 'required' => false],
        ['role_id' => $role->id, 'resource' => 'request-management', 'field' => 'manager_slots', 'visible' => true, 'editable' => true, 'required' => false],
    ]);

    $migration->up();

    $surviving = DB::table('role_field_permissions')->where('role_id', $role->id)->sole();
    expect($surviving->field)->toBe('manager_slots')
        ->and((bool) $surviving->editable)->toBeFalse();
});
