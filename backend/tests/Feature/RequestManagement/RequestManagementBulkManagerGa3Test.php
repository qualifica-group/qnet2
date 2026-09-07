<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Support\ManagerPositions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/**
 * POST /api/request-management/assign-manager-ga3 — spec 0104, direttiva
 * utente 2026-09-07: l'azione massiva sullo slot GA3 (il "Tutor" nella
 * nomenclatura del committente), sorella SENZA SEDE dell'assegnazione
 * operatori.
 *
 * Le tre differenze portanti rispetto ad assign-operators, tutte sotto test
 * qui: nessuna Sede e nessuna modalita' (D-1), lo slot e' svuotabile in massa
 * (D-2), nessuna notifica di assegnazione (D-5).
 */
uses(RefreshDatabase::class);

if (! function_exists('bulkGa3Actor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function bulkGa3Actor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'assignOperator', 'assignManagerGa3'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('bulkGa3Quote')) {
    /**
     * An Offerta whose own `quote_user` team is $slots (position => user id),
     * with `quotes.operator_id` mirroring the OPERATOR slot as
     * QuoteManagerWriter keeps it (spec 0087, INV-2). The Opportunity gets
     * the same slots, which is what makes the actor's D-3 scope resolvable.
     *
     * @param  array<int, int>  $slots
     */
    function bulkGa3Quote(array $slots = []): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $quote = Quote::factory()->for($opportunity)->create([
            'operator_id' => $slots[ManagerPositions::OPERATOR] ?? null,
        ]);

        foreach ($slots as $position => $userId) {
            $quote->managers()->attach($userId, ['position' => $position]);
            $opportunity->managers()->attach($userId, ['position' => $position]);
        }

        return $quote;
    }
}

if (! function_exists('bulkGa3ManagerOf')) {
    /** The user currently sitting in the Offerta's GA3 slot, or null. */
    function bulkGa3ManagerOf(Quote $quote): ?int
    {
        $manager = $quote->fresh()->managers()->wherePivot('position', ManagerPositions::GA3)->first();

        return $manager?->id;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — the ability enters the catalogue
// ---------------------------------------------------------------------------

it('publishes request-management.assignManagerGa3 through the policy catalogue (AC-001)', function () {
    Artisan::call('permissions:sync');

    expect(Permission::query()->where('name', 'request-management.assignManagerGa3')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002/AC-003 — the assignment itself
// ---------------------------------------------------------------------------

it('assigns the chosen user to the GA3 slot of every selected request (AC-002)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $tutor = User::factory()->create();
    $first = bulkGa3Quote();
    $second = bulkGa3Quote();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$first->id, $second->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect(bulkGa3ManagerOf($first))->toBe($tutor->id)
        ->and(bulkGa3ManagerOf($second))->toBe($tutor->id);
});

it('leaves every other slot of the team untouched, the GA2 Operatore included (AC-003)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $operator = User::factory()->create();
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote([ManagerPositions::OPERATOR => $operator->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk();

    $quote->refresh();

    expect($quote->operator_id)->toBe($operator->id)
        ->and($quote->managers()->wherePivot('position', ManagerPositions::OPERATOR)->first()?->id)->toBe($operator->id)
        ->and(bulkGa3ManagerOf($quote))->toBe($tutor->id)
        // No Sede is part of this contract (D-1): the endpoint accepts none
        // and writes none.
        ->and($quote->operational_site_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-004 — clearing the slot in bulk
// ---------------------------------------------------------------------------

it('clears the GA3 slot on the whole batch when manager_ga3_id is null (AC-004)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $operator = User::factory()->create();
    $tutor = User::factory()->create();
    $first = bulkGa3Quote([ManagerPositions::OPERATOR => $operator->id, ManagerPositions::GA3 => $tutor->id]);
    $second = bulkGa3Quote([ManagerPositions::GA3 => $tutor->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$first->id, $second->id],
        'manager_ga3_id' => null,
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect(bulkGa3ManagerOf($first))->toBeNull()
        ->and(bulkGa3ManagerOf($second))->toBeNull()
        ->and($first->fresh()->operator_id)->toBe($operator->id);
});

// ---------------------------------------------------------------------------
// AC-005 — the D-3 scope
// ---------------------------------------------------------------------------

it('skips a request outside the actor D-3 scope instead of failing the batch (AC-005)', function () {
    $actor = bulkGa3Actor(['viewAny', 'update', 'assignManagerGa3']);
    $tutor = User::factory()->create();
    $ownRequest = bulkGa3Quote([ManagerPositions::OPERATOR => $actor->id]);
    $outOfScope = bulkGa3Quote([ManagerPositions::OPERATOR => User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$ownRequest->id, $outOfScope->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect(bulkGa3ManagerOf($ownRequest))->toBe($tutor->id)
        ->and(bulkGa3ManagerOf($outOfScope))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-006 — the two gates
// ---------------------------------------------------------------------------

it('is 403 without request-management.assignManagerGa3, even holding assignOperator (AC-006)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignOperator']);
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertForbidden();

    expect(bulkGa3ManagerOf($quote))->toBeNull();
});

it('is 403 without request-management.update (AC-006)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'view', 'assignManagerGa3']);
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertForbidden();

    expect(bulkGa3ManagerOf($quote))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-007 — validation
// ---------------------------------------------------------------------------

it('rejects an invalid payload with 422 (AC-007)', function (array $payload, string $invalidKey) {
    Sanctum::actingAs(bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']));

    $this->postJson('/api/request-management/assign-manager-ga3', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($invalidKey);
})->with([
    'empty selection' => [['request_ids' => [], 'manager_ga3_id' => null], 'request_ids'],
    'missing selection' => [['manager_ga3_id' => null], 'request_ids'],
    'unknown request id' => [['request_ids' => [99999], 'manager_ga3_id' => null], 'request_ids.0'],
    'unknown user' => [['request_ids' => [], 'manager_ga3_id' => 99999], 'manager_ga3_id'],
    'missing key' => [['request_ids' => []], 'manager_ga3_id'],
]);

// ---------------------------------------------------------------------------
// AC-008/AC-009 — history, and the notification that must NOT be sent
// ---------------------------------------------------------------------------

it('writes one activity entry per changed offer, on the Opportunity (AC-008)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $previousTutor = User::factory()->create();
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote([ManagerPositions::GA3 => $previousTutor->id]);
    $opportunity = $quote->opportunity;
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk();

    $activities = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->get();

    expect($activities)->toHaveCount(1)
        ->and($activities->first()->causer_id)->toBe($actor->id)
        ->and($activities->first()->properties->get('attributes'))->toBe(['manager_ga3_id' => $tutor->id])
        ->and($activities->first()->properties->get('old'))->toBe(['manager_ga3_id' => $previousTutor->id]);
});

it('writes nothing at all when the offer already carries that GA3 (AC-008)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote([ManagerPositions::GA3 => $tutor->id]);
    $opportunity = $quote->opportunity;
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk()->assertJsonPath('data.assigned', 1);

    expect(Activity::query()->where('subject_id', $opportunity->id)->where('event', 'updated')->count())->toBe(0)
        ->and(bulkGa3ManagerOf($quote))->toBe($tutor->id);
});

it('sends no assignment notification: the GA3 scopes nothing and assigns nobody (AC-009)', function () {
    Notification::fake();

    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote();
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk();

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-010 — appartenenza all'Opportunita'
// ---------------------------------------------------------------------------

it('promotes a GA3 who is not yet a manager onto the Opportunity first FREE slot (AC-010)', function () {
    $actor = bulkGa3Actor(['viewAny', 'viewAll', 'update', 'assignManagerGa3']);
    $accountManager = User::factory()->create();
    $tutor = User::factory()->create();
    $quote = bulkGa3Quote([1 => $accountManager->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/request-management/assign-manager-ga3', [
        'request_ids' => [$quote->id],
        'manager_ga3_id' => $tutor->id,
    ])->assertOk();

    // Slot 1 of the Opportunity survives untouched (D-13); the tutor is only
    // APPENDED to the first free one.
    expect($quote->opportunity->managers()->wherePivot('position', 1)->first()?->id)->toBe($accountManager->id);
    $this->assertDatabaseHas('opportunity_user', [
        'opportunity_id' => $quote->opportunity_id,
        'user_id' => $tutor->id,
        'position' => 2,
    ]);
});
