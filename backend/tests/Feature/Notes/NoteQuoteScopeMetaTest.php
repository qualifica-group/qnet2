<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0085 amendment (2026-08-06): the host record's Offerte travel with the
// thread itself (`meta.quotes`, NotableEntity::quoteScopes), so a surface that
// mounts the notes component WITHOUT having loaded the opportunity detail — the
// row-action dialogs of the Opportunita'/Gestione Richieste grids, the work
// panel — still renders the filter and the composer's destination selector.

uses(RefreshDatabase::class);

if (! function_exists('noteMetaActor')) {
    function noteMetaActor(): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo('request-management.view');

        return $user;
    }
}

if (! function_exists('noteMetaOpportunity')) {
    // Spec 0087, D-9: read access is re-keyed on the Opportunity's own
    // Offerte (`quotes.operator_id`) — the GA2 pivot slot no longer grants
    // it on its own. Callers must operate (or grant viewAll for) at least
    // one Offerta of the returned opportunity themselves, via
    // noteMetaQuote(), for a read to succeed.
    function noteMetaOpportunity(User $operator): Opportunity
    {
        return Opportunity::factory()->create();
    }
}

if (! function_exists('noteMetaQuote')) {
    function noteMetaQuote(Opportunity $opportunity, string $code, string $title): Quote
    {
        $quote = Quote::factory()->create(['opportunity_id' => $opportunity->id, 'title' => $title]);
        $quote->forceFill(['code' => $code])->save();

        return $quote;
    }
}

it('exposes the host opportunity Offerte as meta.quotes, ordered by code', function () {
    $actor = noteMetaActor();
    $opportunity = noteMetaOpportunity($actor);
    $second = noteMetaQuote($opportunity, 'QUO-0002', 'Seconda offerta');
    $first = noteMetaQuote($opportunity, 'QUO-0001', 'Prima offerta');
    // D-9: read access needs the actor to operate at least ONE of the
    // opportunity's Offerte — operating one does not narrow which Offerte
    // meta.quotes lists (that stays every Offerta of the opportunity).
    // `operator_id` is deliberately NOT fillable (spec 0087, D-3), so a
    // plain test fixture uses forceFill() rather than a silently-discarded
    // update().
    $first->forceFill(['operator_id' => $actor->id])->save();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertOk();

    expect($response->json('meta.quotes'))->toBe([
        ['id' => $first->id, 'code' => 'QUO-0001', 'title' => 'Prima offerta'],
        ['id' => $second->id, 'code' => 'QUO-0002', 'title' => 'Seconda offerta'],
    ]);
});

// REQUIREMENT CHANGED (spec 0086, D-9): `authorizeRead()` is now keyed on the
// Opportunity's own Offerte existing at all — with zero Offerte there is no
// Quote row for `RequestManagementScope::scopeToActor()` to match against,
// so `exists()` is false for EVERY actor, viewAll included (D-1: a
// `request-management` record is always Quote-backed now). The former "200
// with an empty list" scenario is therefore unreachable; this now documents
// the 403 instead.
it('403s on an opportunity with no Offerta at all — unreadable for anyone (D-9)', function () {
    $actor = noteMetaActor();
    $opportunity = noteMetaOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")
        ->assertForbidden();
});

it('never lists an Offerta of another opportunity', function () {
    $actor = noteMetaActor();
    $opportunity = noteMetaOpportunity($actor);
    $own = noteMetaQuote($opportunity, 'QUO-0001', 'Propria');
    $own->forceFill(['operator_id' => $actor->id])->save();
    noteMetaQuote(Opportunity::factory()->create(), 'QUO-0009', 'Altrui');
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}")->assertOk();

    expect(collect($response->json('meta.quotes'))->pluck('id')->all())->toBe([$own->id]);
});

it('keeps offering every Offerta while the list is filtered on one of them', function () {
    $actor = noteMetaActor();
    $opportunity = noteMetaOpportunity($actor);
    $first = noteMetaQuote($opportunity, 'QUO-0001', 'Prima offerta');
    $second = noteMetaQuote($opportunity, 'QUO-0002', 'Seconda offerta');
    $first->forceFill(['operator_id' => $actor->id])->save();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope={$first->id}")
        ->assertOk();

    // The filter narrows the RESULT, never the choices: the selector must keep
    // offering the scope the operator is not currently reading.
    expect(collect($response->json('meta.quotes'))->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('resolves the Offerte in a single query, whatever their number', function () {
    $actor = noteMetaActor();
    $small = noteMetaOpportunity($actor);
    Quote::factory()->for($small)->create(['operator_id' => $actor->id]);
    $large = noteMetaOpportunity($actor);
    Quote::factory()->for($large)->create(['operator_id' => $actor->id]);
    Quote::factory()->for($large)->count(9)->create();

    Sanctum::actingAs($actor);

    // Warm Spatie's permission cache before measuring, so neither call absorbs
    // a size-unrelated, one-off query.
    $actor->can('request-management.view');
    $this->getJson("/api/notes?entity_type=request-management&entity_id={$small->id}");

    DB::enableQueryLog();
    $this->getJson("/api/notes?entity_type=request-management&entity_id={$small->id}")
        ->assertOk()
        ->assertJsonCount(1, 'meta.quotes');
    $smallCount = count(DB::getQueryLog());
    DB::flushQueryLog();

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$large->id}")
        ->assertOk()
        ->assertJsonCount(10, 'meta.quotes');
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBe($smallCount);
});

it('403s without exposing meta.quotes to an actor who cannot read the host record', function () {
    $actor = noteMetaActor();
    $foreign = Opportunity::factory()->create();
    noteMetaQuote($foreign, 'QUO-0001', 'Riservata');
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$foreign->id}")->assertForbidden();
});
