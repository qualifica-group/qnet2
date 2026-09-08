<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Role;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Direttiva utente 2026-09-08 — the third state of the work panel's team
 * block: `request-management.appendTeamMember` lets an actor whose
 * `manager_slots` is visible but NOT editable ADD members to the squadra,
 * while every member already there stays frozen — no reassignment, no
 * reorder, no removal, and no filling of a gap left inside the persisted
 * arrangement ("congelati, si aggiunge in coda").
 */
uses(RefreshDatabase::class);

if (! function_exists('appendOnlyActor')) {
    /**
     * A role-bearing actor: only a ROLE carries a `role_field_permissions`
     * row, and the append-only state only exists on a `manager_slots` that
     * row locked.
     *
     * @param  array<int, string>  $abilities  request-management ability names
     * @param  array<string, mixed>|null  $matrixRow  a single role_field_permissions row
     */
    function appendOnlyActor(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'appendTeamMember'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $role = Role::create(['name' => 'append-only-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "request-management.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

if (! function_exists('appendOnlyMatrixRow')) {
    /**
     * The locked-but-visible `manager_slots` row the whole grant hangs off.
     *
     * @return array<string, mixed>
     */
    function appendOnlyMatrixRow(bool $visible = true): array
    {
        return [
            'resource' => 'request-management',
            'field' => 'manager_slots',
            'visible' => $visible,
            'editable' => false,
            'required' => false,
        ];
    }
}

if (! function_exists('appendOnlyQuote')) {
    /**
     * An Offerta whose `quote_user` team is $slots (position => user id),
     * with `quotes.operator_id` mirroring the OPERATOR slot as
     * QuoteManagerWriter keeps it (spec 0087, INV-2).
     *
     * @param  array<int, int>  $slots
     */
    function appendOnlyQuote(array $slots): Quote
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

if (! function_exists('appendOnlyTeam')) {
    /**
     * The persisted team as `position => userId`, the vocabulary every
     * assertion below reads.
     *
     * @return array<int, int>
     */
    function appendOnlyTeam(Quote $quote): array
    {
        return $quote->fresh()->managers()->get()
            ->mapWithKeys(static fn (User $manager): array => [(int) $manager->pivot->position => (int) $manager->id])
            ->all();
    }
}

// ---------------------------------------------------------------------------
// The metadata flag the panel reads (permissions.actions.append_team_member)
// ---------------------------------------------------------------------------

it('exposes append_team_member with both request-management.update and .appendTeamMember', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $quote = appendOnlyQuote([]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.append_team_member', true);
});

it('does not expose append_team_member without the ability', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update'], appendOnlyMatrixRow());
    $quote = appendOnlyQuote([]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.append_team_member', false);
});

it('does not expose append_team_member without request-management.update', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'appendTeamMember'], appendOnlyMatrixRow());
    $quote = appendOnlyQuote([]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/request-management/{$quote->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.append_team_member', false);
});

// ---------------------------------------------------------------------------
// What the grant ALLOWS: adding to the tail of a locked team
// ---------------------------------------------------------------------------

it('lets the grant holder append a member beyond the persisted team', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::GA1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$first->id, $operator->id, $newcomer->id, null],
    ])->assertOk();

    expect(appendOnlyTeam($quote))->toBe([
        ManagerPositions::GA1 => $first->id,
        ManagerPositions::OPERATOR => $operator->id,
        3 => $newcomer->id,
    ]);
});

it('lets the grant holder compose the team of an Offerta that has none yet', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $quote = appendOnlyQuote([]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$newcomer->id, null],
    ])->assertOk();

    expect(appendOnlyTeam($quote))->toBe([ManagerPositions::GA1 => $newcomer->id]);
});

// ---------------------------------------------------------------------------
// What it does NOT allow: every shape that touches the frozen prefix
// ---------------------------------------------------------------------------

it('rejects removing a member of the persisted team', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::GA1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$first->id, null],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect(appendOnlyTeam($quote))->toBe([
        ManagerPositions::GA1 => $first->id,
        ManagerPositions::OPERATOR => $operator->id,
    ]);
});

it('rejects replacing the occupant of a persisted slot', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $newOperator->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect($quote->fresh()->operator_id)->toBe($operator->id);
});

it('rejects reordering the persisted team', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $first = User::factory()->create();
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::GA1 => $first->id, ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$operator->id, $first->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect(appendOnlyTeam($quote))->toBe([
        ManagerPositions::GA1 => $first->id,
        ManagerPositions::OPERATOR => $operator->id,
    ]);
});

it('rejects filling a gap left inside the persisted team', function () {
    // Decisione utente 2026-09-08: an empty slot INSIDE the arrangement is
    // part of what is frozen — only the tail belongs to the grant holder.
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [$newcomer->id, $operator->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect(appendOnlyTeam($quote))->toBe([ManagerPositions::OPERATOR => $operator->id]);
});

it('freezes the OPERATOR slot even when only the denormalized column names one', function () {
    // `quotes.operator_id` mirrors pivot slot 2 (spec 0087, INV-2) and it is
    // the slot that decides row visibility (spec 0049, D-3): an append must
    // never be able to hand it over, so the guard reads the column too and
    // does not depend on the pivot row being there.
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow());
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([]);
    $quote->forceFill(['operator_id' => $operator->id])->save();
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $newcomer->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    // …while the tail past that slot stays open, operator kept in place.
    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $operator->id, $newcomer->id],
    ])->assertOk();

    expect(appendOnlyTeam($quote))->toBe([
        ManagerPositions::OPERATOR => $operator->id,
        3 => $newcomer->id,
    ]);
});

it('rejects an append from an actor without the ability', function () {
    $actor = appendOnlyActor(['view', 'viewAll', 'update'], appendOnlyMatrixRow());
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $operator->id, $newcomer->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect(appendOnlyTeam($quote))->toBe([ManagerPositions::OPERATOR => $operator->id]);
});

it('rejects an append when the role hides the team block entirely', function () {
    // The grant reads "see the squadra and only add to it": with nothing
    // visible there is nothing to append to, and a crafted payload must not
    // become the one way to write a hidden field.
    $actor = appendOnlyActor(['view', 'viewAll', 'update', 'appendTeamMember'], appendOnlyMatrixRow(visible: false));
    $operator = User::factory()->create();
    $quote = appendOnlyQuote([ManagerPositions::OPERATOR => $operator->id]);
    $newcomer = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'manager_slots' => [null, $operator->id, $newcomer->id],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_slots');

    expect(appendOnlyTeam($quote))->toBe([ManagerPositions::OPERATOR => $operator->id]);
});
