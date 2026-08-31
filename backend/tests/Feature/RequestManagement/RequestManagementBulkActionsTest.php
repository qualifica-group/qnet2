<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * User directive 2026-07-23: the request-management grid gets the selection
 * checkbox column, which the generic table only shows when a bulk action is
 * reachable — bulk delete and bulk operator assignment, "come nei lead".
 * Spec 0086, D-1: the row is now a `quotes` record, and the assigned
 * Sede/GA2 Operatore now land on `quotes.*` (spec 0087, D-9).
 *
 * The load-bearing rule under test is D-2: both flows are gated by this
 * module's OWN `request-management.*` permissions, never `opportunities.*`,
 * and both respect the D-9 operator scope.
 */
if (! function_exists('bulkActionsActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function bulkActionsActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'delete', 'viewAll', 'assignOperator'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('bulkActionsOperatorAtSite')) {
    function bulkActionsOperatorAtSite(OperationalSite $site): User
    {
        $operator = User::factory()->create();
        EmploymentProfile::factory()->create(['user_id' => $operator->id, 'operational_site_id' => $site->id]);

        return $operator;
    }
}

if (! function_exists('bulkActionsRequestManagedBy')) {
    function bulkActionsRequestManagedBy(User $operator): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->attach($operator->id, ['position' => Opportunity::OPERATOR_MANAGER_POSITION]);

        return Quote::factory()->for($opportunity)->create(['operator_id' => $operator->id]);
    }
}

// ---------------------------------------------------------------------------
// The `delete` action: what switches the checkbox column on
// ---------------------------------------------------------------------------

it('the action catalogue exposes `delete` to an actor holding request-management.delete', function () {
    Sanctum::actingAs(bulkActionsActor(['viewAny', 'view', 'delete']));

    $keys = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions'))
        ->pluck('key');

    expect($keys)->toContain('delete')
        ->and($keys)->not->toContain('edit');
});

it('the `delete` action is hidden from an actor without request-management.delete', function () {
    Sanctum::actingAs(bulkActionsActor(['viewAny', 'view']));

    $keys = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.actions'))
        ->pluck('key');

    expect($keys)->not->toContain('delete');
});

it('row.actions carries `delete` only for an actor holding request-management.delete', function () {
    $quote = Quote::factory()->create();

    Sanctum::actingAs(bulkActionsActor(['viewAny', 'viewAll', 'view']));
    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->not->toContain('delete');

    Sanctum::actingAs(bulkActionsActor(['viewAny', 'viewAll', 'view', 'delete']));
    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($items->firstWhere('id', $quote->id)['actions'])->toContain('delete');
});

// ---------------------------------------------------------------------------
// Bulk delete
// ---------------------------------------------------------------------------

it('bulk-delete removes every selected request for an actor holding request-management.delete', function () {
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();
    Sanctum::actingAs(bulkActionsActor(['viewAny', 'viewAll', 'delete']));

    $this->postJson('/api/tables/request-management/bulk-delete', ['ids' => [$first->id, $second->id]])
        ->assertOk()
        ->assertJsonPath('data.deleted', 2)
        ->assertJsonPath('data.failed', []);

    expect(Quote::query()->whereIn('id', [$first->id, $second->id])->count())->toBe(0);
});

it('bulk-delete is gated by request-management.delete, NOT quotes.delete (D-2)', function () {
    $quote = Quote::factory()->create();
    $actor = bulkActionsActor(['viewAny', 'viewAll']);
    // The FOREIGN permission the default Gate check would have resolved.
    Permission::findOrCreate('quotes.delete');
    $actor->givePermissionTo('quotes.delete');
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/bulk-delete', ['ids' => [$quote->id]])
        ->assertOk()
        ->assertJsonPath('data.deleted', 0)
        ->assertJsonPath('data.failed.0.reason', 'forbidden');

    expect(Quote::query()->whereKey($quote->id)->exists())->toBeTrue();
});

it('bulk-delete never reaches a request outside the actor D-3 scope', function () {
    $someoneElse = User::factory()->create();
    $outOfScope = bulkActionsRequestManagedBy($someoneElse);
    $actor = bulkActionsActor(['viewAny', 'delete']);
    $ownRequest = bulkActionsRequestManagedBy($actor);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/request-management/bulk-delete', ['ids' => [$ownRequest->id, $outOfScope->id]])
        ->assertOk()
        ->assertJsonPath('data.deleted', 1)
        ->assertJsonPath('data.failed.0.reason', 'not_found');

    expect(Quote::query()->whereKey($outOfScope->id)->exists())->toBeTrue()
        ->and(Quote::query()->whereKey($ownRequest->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Single-row delete (the row action behind the same permission)
// ---------------------------------------------------------------------------

it('DELETE /request-management/{id} removes the request for a permitted, in-scope actor, AND THE OPPORTUNITY SURVIVES (AC-031)', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'delete']);
    $quote = Quote::factory()->create();
    $opportunityId = $quote->opportunity_id;
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/request-management/{$quote->id}")->assertNoContent();

    expect(Quote::query()->whereKey($quote->id)->exists())->toBeFalse()
        ->and(Opportunity::query()->whereKey($opportunityId)->exists())->toBeTrue();
});

it('DELETE /request-management/{id} is 403 without request-management.delete', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'view']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/request-management/{$quote->id}")->assertForbidden();

    expect(Quote::query()->whereKey($quote->id)->exists())->toBeTrue();
});

it('DELETE /request-management/{id} is 403 on a request the actor does not supervise (D-3)', function () {
    $actor = bulkActionsActor(['viewAny', 'delete']);
    $quote = bulkActionsRequestManagedBy(User::factory()->create());
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/request-management/{$quote->id}")->assertForbidden();

    expect(Quote::query()->whereKey($quote->id)->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Bulk operator assignment
// ---------------------------------------------------------------------------

it('mode=single assigns the Sede and the GA2 operator to every selected request', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = bulkActionsOperatorAtSite($site);
    $first = Quote::factory()->create();
    $second = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$first->id, $second->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    // Spec 0087, D-9/D-14/AC-017: writes `quotes.operator_id`, never
    // `quotes.supervisor_id`. D-13: promoted onto the Opportunity's first
    // FREE slot — both Opportunities here were born with zero managers, so
    // that is slot 1, not the GA2 slot `operatorManager()` reads.
    expect($first->fresh()->operational_site_id)->toBe($site->id)
        ->and($first->fresh()->operator_id)->toBe($operator->id)
        ->and($first->fresh()->supervisor_id)->toBeNull()
        ->and($first->fresh()->opportunity->operatorManager())->toBeNull()
        ->and($second->fresh()->operator_id)->toBe($operator->id);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $first->fresh()->opportunity_id,
        'user_id' => $operator->id,
        'position' => 1,
    ]);
});

it('the assignment writes the Offerta\'s own operator_id and promotes onto the Opportunity\'s first free slot, without touching any existing manager slot (spec 0087, D-9/D-13)', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $nextOperator = bulkActionsOperatorAtSite($site);
    $accountManager = User::factory()->create();
    $originalOperator = User::factory()->create();
    $quote = bulkActionsRequestManagedBy($originalOperator);
    $quote->opportunity->managers()->attach($accountManager->id, ['position' => 1]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $nextOperator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    $quote->refresh();
    // D-13: the Opportunity's slot 1 (accountManager) AND slot 2
    // (originalOperator, this offer's GA2) both survive untouched —
    // $nextOperator is only APPENDED to the first free slot, 3.
    expect($quote->operator_id)->toBe($nextOperator->id)
        ->and($quote->opportunity->managers()->wherePivot('position', 1)->first()?->id)->toBe($accountManager->id)
        ->and($quote->opportunity->operatorManager()?->id)->toBe($originalOperator->id);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $nextOperator->id,
        'position' => 3,
    ]);
});

it('mode=balanced spreads the selected requests across the Sede operators', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $firstOperator = bulkActionsOperatorAtSite($site);
    $secondOperator = bulkActionsOperatorAtSite($site);
    $quotes = Quote::factory()->count(4)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => $quotes->modelKeys(),
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertOk()->assertJsonPath('data.assigned', 4);

    $loads = $quotes->map(fn (Quote $quote): ?int => $quote->fresh()->operator_id)
        ->countBy()
        ->all();

    expect($loads)->toBe([$firstOperator->id => 2, $secondOperator->id => 2]);
});

it('mode=balanced is 422 when the chosen Sede has no operators', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'balanced',
    ])->assertStatus(422);

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('the assignment skips a request outside the actor D-3 scope', function () {
    $actor = bulkActionsActor(['viewAny', 'update', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = bulkActionsOperatorAtSite($site);
    $ownRequest = bulkActionsRequestManagedBy($actor);
    $outOfScope = bulkActionsRequestManagedBy(User::factory()->create());
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$ownRequest->id, $outOfScope->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect($ownRequest->fresh()->operator_id)->toBe($operator->id)
        ->and($outOfScope->fresh()->operational_site_id)->toBeNull();
});

/**
 * User directive 2026-08-03: this endpoint writes the Sede AND the Operatore
 * of many requests at once, and a bulk write resolves no field permission —
 * without its own ability it would be the way around a per-field restriction
 * (see TestUsersSeeder's Commercial matrix).
 */
it('the assignment endpoint is 403 without request-management.assignOperator', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'update']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = bulkActionsOperatorAtSite($site);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});

it('the assignment endpoint is 403 without request-management.update', function () {
    $actor = bulkActionsActor(['viewAny', 'viewAll', 'view', 'assignOperator']);
    $site = OperationalSite::factory()->withAddress()->create();
    $operator = bulkActionsOperatorAtSite($site);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-operators', [
        'request_ids' => [$quote->id],
        'operational_site_id' => $site->id,
        'mode' => 'single',
        'operator_id' => $operator->id,
    ])->assertForbidden();

    expect($quote->fresh()->operational_site_id)->toBeNull();
});
