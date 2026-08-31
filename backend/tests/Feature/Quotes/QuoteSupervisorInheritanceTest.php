<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * User directive 2026-08-31 (supersedes 2026-08-06): on an Offerta the
 * Supervisore is ALWAYS inherited from the Opportunita's own Supervisore,
 * with no Gestore Account restriction anywhere. The three surfaces that used
 * to enforce that restriction must now agree on its absence: the picker
 * (`users/for-select`, no longer accepting an `opportunity_id` scope), the
 * write side (no guard) and the create-time inheritance
 * (QuoteService::applySnapshotDefaults + the for-select `meta` the form
 * prefills from).
 *
 * Replaces QuoteSupervisorManagerTest, whose every assertion encoded the
 * revoked rule.
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

// ---------------------------------------------------------------------------
// The list: users/for-select is never scoped to an opportunity any more.
// ---------------------------------------------------------------------------

it('lists every user in users/for-select, managers and outsiders alike', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->attach($manager->id, ['position' => 1]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $ids = collect($this->getJson('/api/users/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($manager->id)->and($ids)->toContain($outsider->id);
});

it('ignores a stray opportunity_id on users/for-select instead of narrowing by it', function () {
    $manager = User::factory()->create();
    $outsider = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $opportunity->managers()->attach($manager->id, ['position' => 1]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $ids = collect(
        $this->getJson("/api/users/for-select?opportunity_id={$opportunity->id}")->assertOk()->json('items')
    )->pluck('id');

    expect($ids)->toContain($manager->id)->and($ids)->toContain($outsider->id);
});

// ---------------------------------------------------------------------------
// The write side: any user is an acceptable Supervisore.
// ---------------------------------------------------------------------------

it('creates an offer whose supervisor is not a Gestore Account of the opportunity', function () {
    $outsider = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Supervisore non gestore',
        'opportunity_id' => $opportunity->id,
        'supervisor_id' => $outsider->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $outsider->id);
});

it('accepts on update a supervisor who is not a Gestore Account of the persisted opportunity', function () {
    $outsider = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => null]);
    Sanctum::actingAs(quoteSupervisorUserWith(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['supervisor_id' => $outsider->id])->assertOk();

    expect($quote->fresh()->supervisor_id)->toBe($outsider->id);
});

it('always accepts clearing the supervisor', function () {
    $manager = User::factory()->create();
    $opportunity = Opportunity::factory()->create();
    $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'supervisor_id' => $manager->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['update']));

    $this->patchJson("/api/quotes/{$quote->id}", ['supervisor_id' => null])->assertOk();

    expect($quote->fresh()->supervisor_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// The inheritance: an unconditional copy of the opportunity's Supervisore.
// This is the case the revoked GA filter silently nulled on the whole dataset.
// ---------------------------------------------------------------------------

it('inherits the opportunity supervisor even when they hold no Gestore Account slot', function () {
    $outsider = User::factory()->create();
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $outsider->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Eredita comunque',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $outsider->id);
});

it('inherits the opportunity supervisor when they are also a Gestore Account', function () {
    $manager = User::factory()->create();
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $manager->id]);
    $opportunity->managers()->attach($manager->id, ['position' => 1]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Eredita il gestore',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $manager->id);
});

it('lets an explicitly submitted supervisor win over the inherited one', function () {
    $inherited = User::factory()->create();
    $chosen = User::factory()->create();
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $inherited->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Scelta esplicita',
        'opportunity_id' => $opportunity->id,
        'supervisor_id' => $chosen->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', $chosen->id);
});

it('inherits nothing when the opportunity has no supervisor', function () {
    $opportunity = Opportunity::factory()->create(['supervisor_id' => null]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $this->postJson('/api/quotes', [
        'title' => 'Nessun supervisore',
        'opportunity_id' => $opportunity->id,
    ])->assertCreated()->assertJsonPath('data.supervisor_id', null);
});

it('exposes a non-Gestore-Account supervisor in the opportunity for-select meta', function () {
    $outsider = User::factory()->create(['name' => 'Estraneo Rossi']);
    $opportunity = Opportunity::factory()->create(['supervisor_id' => $outsider->id]);
    Sanctum::actingAs(quoteSupervisorUserWith(['create']));

    $items = collect($this->getJson('/api/opportunities/for-select')->assertOk()->json('items'));

    expect($items->firstWhere('id', $opportunity->id)['meta']['supervisor'])
        ->toBe(['id' => $outsider->id, 'name' => $outsider->name]);
});
