<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User directive 2026-08-06: on an Offerta the Supervisore can ONLY be a
 * Gestore Account of its Opportunita'. Three surfaces must agree:
 * the scoped picker (`users/for-select?opportunity_id=`), the write-side
 * guard (ValidatesQuoteSupervisor on Store/Update) and the create-time
 * inheritance (QuoteService::applySnapshotDefaults + the for-select `meta`
 * the form prefills from).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteSupervisorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteSupervisorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteSupervisorOpportunityWithManager')) {
    /** An opportunity plus one Gestore Account attached at the given pivot position. */
    function quoteSupervisorOpportunityWithManager(User $manager, int $position = 1): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->attach($manager->id, ['position' => $position]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// The list: users/for-select scoped to the opportunity's Gestori Account.
// ---------------------------------------------------------------------------

it('restricts users/for-select to the opportunity Gestori Account', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    $actor = quoteSupervisorUserWith(['create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?opportunity_id={$opportunity->id}")->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($manager->id)
        ->and($ids)->not->toContain($outsider->id)
        ->and($ids)->not->toContain($actor->id);
});

it('leaves users/for-select unscoped when no opportunity_id is passed', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    quoteSupervisorOpportunityWithManager($manager);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $ids = collect($this->getJson('/api/users/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($manager->id)->and($ids)->toContain($outsider->id);
});

it('rejects an opportunity_id that does not exist (422)', function () {
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->getJson('/api/users/for-select?opportunity_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('opportunity_id');
});

// ---------------------------------------------------------------------------
// The write side: a non-GA supervisor is rejected on create and on update.
// ---------------------------------------------------------------------------

it('creates an offer whose supervisor is a Gestore Account of the opportunity', function () {
    $manager = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Con supervisore GA',
        'opportunity_id' => $opportunity->id,
        'supervisor_id' => $manager->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $manager->id);
});

it('rejects a supervisor who is not a Gestore Account of the opportunity (422)', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Con supervisore estraneo',
        'opportunity_id' => $opportunity->id,
        'supervisor_id' => $outsider->id,
    ])->assertStatus(422)->assertJsonValidationErrors('supervisor_id');

    expect(Quote::count())->toBe(0);
});

it('rejects on update a supervisor who is not a Gestore Account of the persisted opportunity (422)', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $manager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['supervisor_id' => $outsider->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('supervisor_id');

    expect($quote->fresh()->supervisor_id)->toBe($manager->id);
});

it('accepts on update a supervisor who holds another Gestore Account slot', function () {
    $firstManager = User::factory()->create();
    $secondManager = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($firstManager);
    $opportunity->managers()->attach($secondManager->id, ['position' => 2]);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $firstManager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['supervisor_id' => $secondManager->id])->assertOk();

    expect($quote->fresh()->supervisor_id)->toBe($secondManager->id);
});

it('always accepts clearing the supervisor', function () {
    $manager = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $manager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['supervisor_id' => null])->assertOk();

    expect($quote->fresh()->supervisor_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// The inheritance: only a Supervisore who is ALSO a Gestore Account is copied.
// ---------------------------------------------------------------------------

it('inherits the opportunity supervisor when they are also a Gestore Account', function () {
    $manager = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    $opportunity->update(['supervisor_id' => $manager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Eredita il GA',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $manager->id);
});

it('inherits nothing when the opportunity supervisor is not a Gestore Account', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = quoteSupervisorOpportunityWithManager($manager);
    $opportunity->update(['supervisor_id' => $outsider->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Non eredita un estraneo',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', null);
});

it('omits a non-Gestore-Account supervisor from the opportunity for-select meta', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create(['name' => 'Estraneo Rossi']);
    $withOutsider = quoteSupervisorOpportunityWithManager($manager);
    $withOutsider->update(['supervisor_id' => $outsider->id]);
    $withManager = quoteSupervisorOpportunityWithManager($manager);
    $withManager->update(['supervisor_id' => $manager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $items = collect($this->getJson('/api/opportunities/for-select')->assertOk()->json('items'));

    expect($items->firstWhere('id', $withOutsider->id)['meta']['supervisor'])->toBeNull()
        ->and($items->firstWhere('id', $withManager->id)['meta']['supervisor'])
        ->toBe(['id' => $manager->id, 'name' => $manager->name]);
});
