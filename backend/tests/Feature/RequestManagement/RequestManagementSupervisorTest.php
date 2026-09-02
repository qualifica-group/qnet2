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
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0097, D-9 (user directive 2026-09-02) — "Supervisore" is written from
 * Gestione Richieste again, from BOTH form channels, after spec 0087
 * D-13/D-14 had removed it from this module entirely.
 *
 * What comes back is the NARROW field: the Offerta's commission recipient
 * (`quotes.supervisor_id`, a fillable scalar), never the ownership column the
 * deleted RequestSupervisorWriter kept in lockstep with the GA2 slot. Hence
 * AC-014 below: the two dimensions must be provably independent, in both
 * directions, notifications included.
 */
uses(RefreshDatabase::class);

if (! function_exists('supervisorFieldActor')) {
    /**
     * @param  array<int, string>  $abilities  request-management ability names
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function supervisorFieldActor(array $abilities = ['viewAny', 'view', 'update', 'viewAll'], ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'supervisor-field-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('supervisorFieldQuote')) {
    /**
     * An Offerta with a GA2 "Operatore" on its own pivot (mirrored on
     * `quotes.operator_id` as QuoteManagerWriter keeps it) and an optional
     * Supervisore — the two dimensions AC-014 keeps apart.
     *
     * A decoy Opportunity shifts the two id sequences apart, so an assertion
     * mixing up quote and opportunity ids can never pass by coincidence.
     */
    function supervisorFieldQuote(?User $operator = null, ?User $supervisor = null): Quote
    {
        Opportunity::factory()->create();

        $quote = Quote::factory()->create([
            'operator_id' => $operator?->id,
            'supervisor_id' => $supervisor?->id,
        ]);

        if ($operator !== null) {
            $quote->managers()->attach($operator->id, ['position' => ManagerPositions::OPERATOR]);
        }

        return $quote;
    }
}

if (! function_exists('supervisorFieldCreatePayload')) {
    /**
     * The smallest valid POST body (registry branch + one product line + the
     * mandatory Fonte) — local to this file, so running it alone resolves.
     *
     * @return array<string, mixed>
     */
    function supervisorFieldCreatePayload(): array
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
// AC-013 — the panel channel: read, write, validate
// ---------------------------------------------------------------------------

it('AC-013: GET exposes the supervisor id and its picker ref', function () {
    $actor = supervisorFieldActor();
    $supervisor = User::factory()->create();
    $quote = supervisorFieldQuote(supervisor: $supervisor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.supervisor_id', $supervisor->id)
        ->assertJsonPath('data.supervisor.id', $supervisor->id)
        ->assertJsonPath('data.supervisor.name', $supervisor->name);
});

it('AC-013: GET reports a null supervisor when there is none', function () {
    $actor = supervisorFieldActor();
    $quote = supervisorFieldQuote();
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('data.supervisor_id', null)
        ->assertJsonPath('data.supervisor', null);
});

it('AC-013: PATCH writes the supervisor and returns it on the same response', function () {
    $actor = supervisorFieldActor();
    $quote = supervisorFieldQuote();
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'supervisor_id' => $supervisor->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.supervisor_id', $supervisor->id)
        ->assertJsonPath('data.supervisor.name', $supervisor->name);

    expect($quote->fresh()->supervisor_id)->toBe($supervisor->id);
});

it('AC-013: PATCH clears the supervisor with an explicit null and leaves it untouched when the key is absent', function () {
    $actor = supervisorFieldActor();
    $supervisor = User::factory()->create();
    $quote = supervisorFieldQuote(supervisor: $supervisor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['next_callback_at' => '2026-09-10T09:00'])->assertOk();
    expect($quote->fresh()->supervisor_id)->toBe($supervisor->id);

    $this->patchJson("/api/request-management/{$quote->id}", ['supervisor_id' => null])
        ->assertOk()
        ->assertJsonPath('data.supervisor_id', null);

    expect($quote->fresh()->supervisor_id)->toBeNull();
});

it('AC-013: PATCH rejects a supervisor that is not an existing user', function () {
    $actor = supervisorFieldActor();
    $quote = supervisorFieldQuote();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['supervisor_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('supervisor_id');
});

it('AC-013: the supervisor change is logged on the OPPORTUNITY thread', function () {
    $actor = supervisorFieldActor();
    $previous = User::factory()->create();
    $quote = supervisorFieldQuote(supervisor: $previous);
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", ['supervisor_id' => $supervisor->id])->assertOk();

    $properties = Activity::query()
        ->where('subject_type', $quote->opportunity->getMorphClass())
        ->where('subject_id', $quote->opportunity_id)
        ->where('event', 'updated')
        ->sole()
        ->properties;

    expect($properties->get('attributes'))->toMatchArray(['supervisor_id' => $supervisor->id])
        ->and($properties->get('old'))->toMatchArray(['supervisor_id' => $previous->id]);
});

// ---------------------------------------------------------------------------
// AC-013 — per-field gating: a role that may not edit it gets a 422
// ---------------------------------------------------------------------------

it('AC-013: a role with supervisor_id non-editable gets a 422 on a real change', function () {
    $actor = supervisorFieldActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'supervisor_id', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $supervisor = User::factory()->create();
    $quote = supervisorFieldQuote(supervisor: $supervisor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'supervisor_id' => User::factory()->create()->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('supervisor_id');

    expect($quote->fresh()->supervisor_id)->toBe($supervisor->id);
});

it('AC-013: resubmitting the CURRENT supervisor on a non-editable field is a no-op, not a 422', function () {
    $actor = supervisorFieldActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'supervisor_id', 'visible' => true, 'editable' => false, 'required' => false],
    );
    $supervisor = User::factory()->create();
    $quote = supervisorFieldQuote(supervisor: $supervisor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'supervisor_id' => $supervisor->id,
    ])->assertOk();

    expect($quote->fresh()->supervisor_id)->toBe($supervisor->id);
});

it('AC-013: the panel envelope carries the field permission', function () {
    $actor = supervisorFieldActor(
        ['viewAny', 'view', 'update', 'viewAll'],
        ['resource' => 'request-management', 'field' => 'supervisor_id', 'visible' => false, 'editable' => false, 'required' => false],
    );
    $quote = supervisorFieldQuote();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/request-management/{$quote->id}")->assertOk()->json('permissions.fields');

    expect($fields['supervisor_id']['visible'])->toBeFalse()
        ->and($fields['supervisor_id']['hidden'])->toBeTrue()
        ->and($fields['supervisor_id']['editable'])->toBeFalse()
        ->and($fields['supervisor_id']['required'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-014 — supervisor_id and operator_id/slot 2 are INDEPENDENT
// ---------------------------------------------------------------------------

it('AC-014: writing the supervisor moves no slot and notifies nobody', function () {
    Notification::fake();

    $actor = supervisorFieldActor();
    $operator = User::factory()->create();
    $quote = supervisorFieldQuote($operator);
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'supervisor_id' => $supervisor->id,
    ])->assertOk();

    Notification::assertNothingSent();

    $fresh = $quote->fresh();
    expect($fresh->supervisor_id)->toBe($supervisor->id)
        ->and($fresh->operator_id)->toBe($operator->id)
        ->and($quote->managers()->pluck('users.id')->all())->toBe([$operator->id]);

    $this->assertDatabaseMissing('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $supervisor->id,
    ]);
});

it('AC-014: writing the team leaves the supervisor exactly where it was', function () {
    Notification::fake();

    $actor = supervisorFieldActor();
    $operator = User::factory()->create();
    $supervisor = User::factory()->create();
    $quote = supervisorFieldQuote($operator, $supervisor);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $newOperator->id],
    ])->assertOk();

    Notification::assertSentTo($newOperator, RecordAssignmentNotification::class);

    $fresh = $quote->fresh();
    expect($fresh->operator_id)->toBe($newOperator->id)
        ->and($fresh->supervisor_id)->toBe($supervisor->id);
});

it('AC-014: both dimensions in one PATCH land on their own storage', function () {
    $actor = supervisorFieldActor();
    $quote = supervisorFieldQuote();
    $operator = User::factory()->create();
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $operator->id],
        'supervisor_id' => $supervisor->id,
    ])->assertOk();

    $fresh = $quote->fresh();
    expect($fresh->operator_id)->toBe($operator->id)
        ->and($fresh->supervisor_id)->toBe($supervisor->id);

    $this->assertDatabaseHas('quote_user', [
        'quote_id' => $quote->id,
        'user_id' => $operator->id,
        'position' => ManagerPositions::OPERATOR,
    ]);
});

// ---------------------------------------------------------------------------
// AC-013 — the creation channel
// ---------------------------------------------------------------------------

it('AC-013: POST stores the submitted supervisor on the created Offerta and returns it', function () {
    $actor = supervisorFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    $supervisor = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', [
        ...supervisorFieldCreatePayload(),
        'supervisor_id' => $supervisor->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.supervisor_id', $supervisor->id)
        ->assertJsonPath('data.supervisor.name', $supervisor->name);

    $quote = Quote::findOrFail($response->json('data.id'));

    expect($quote->supervisor_id)->toBe($supervisor->id)
        // The creating actor is the OPERATOR by default (spec 0097, AC-002):
        // the new field must not have displaced that default.
        ->and($quote->operator_id)->toBe($actor->id);
});

it('AC-013: POST without the key leaves the creation exactly as it was', function () {
    $actor = supervisorFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/request-management', supervisorFieldCreatePayload())
        ->assertCreated()
        ->assertJsonPath('data.supervisor_id', null);

    expect(Quote::findOrFail($response->json('data.id'))->supervisor_id)->toBeNull();
});

it('AC-013: POST rejects a supervisor that is not an existing user', function () {
    $actor = supervisorFieldActor(['viewAny', 'view', 'create', 'update', 'viewAll']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management', [
        ...supervisorFieldCreatePayload(),
        'supervisor_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('supervisor_id');
});
