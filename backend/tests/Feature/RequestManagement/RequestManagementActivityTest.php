<?php

use App\Models\Attachment;
use App\Models\Note;
use App\Models\Opportunity;
use App\Models\PersonalData;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-043 — write side: RequestManagementService::updateWork() writes an
// explicit activity() entry on the Opportunity for every operative PATCH
// (next_callback_at — spec 0083 D-2 removed the former working-status field
// from this channel entirely, and spec 0084 D-1 the attribute_values one),
// which logFillable() cannot capture (that column is outside $fillable).
//
// Read side (D-7, AMENDED — user request 2026-07-22): the module DOES expose
// its own activity surface, `GET /api/activity-log/request-management/{id}`.
// The former blocker — the generic controller resolving the Policy by MODEL
// CLASS (Opportunity), i.e. gating by `opportunities.*` — is gone: the
// resource declares its own ActivityLogAuthorizer
// (RequestManagementActivityAuthorizer), so the gate is
// `request-management.viewActivity` PLUS the D-3 scope, re-keyed on the
// Opportunity's own Offerte (spec 0086, D-9: the {id} route param stays the
// Opportunity, but authorization requires the actor to supervise at least
// one of its Offerte). The timeline aggregates the request, its notes
// (soft-deleted included), its documents and the client anagraphic block
// edited from the panel.
// ---------------------------------------------------------------------------

if (! function_exists('requestManagementActivityUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementActivityUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll', 'viewActivity'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('PATCH /api/request-management/{id} writes exactly one activity entry on the Opportunity (AC-043)', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'update', 'viewAll']);
    $quote = Quote::factory()->create();
    $opportunity = $quote->opportunity;
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');

    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => $callbackAt,
    ])->assertOk();

    $activities = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->get();

    expect($activities)->toHaveCount(1);
    expect($activities->first()->causer_id)->toBe($actor->id);
    expect($activities->first()->properties->get('attributes'))->toHaveKey('next_callback_at');
});

it('exposes the operative change through the request-management resource key, with NO opportunities.* permission', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'update', 'viewAll', 'viewActivity']);
    $quote = Quote::factory()->create();
    $opportunity = $quote->opportunity;
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');

    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'next_callback_at' => $callbackAt,
    ])->assertOk();

    $this->getJson("/api/activity-log/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.items.0.module', $opportunity->getMorphClass())
        ->assertJsonPath('data.items.0.changes.0.field', 'next_callback_at');
});

it('denies the request-management activity log without request-management.viewActivity, even holding opportunities.viewActivity', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'viewAll']);
    Permission::findOrCreate('opportunities.viewActivity');
    Permission::findOrCreate('opportunities.view');
    $actor->givePermissionTo(['opportunities.viewActivity', 'opportunities.view']);

    $opportunity = Opportunity::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/request-management/{$opportunity->id}")->assertForbidden();
});

it('denies the request-management activity log to an actor outside the D-3 scope', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'viewActivity']);
    $opportunity = Opportunity::factory()->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/activity-log/request-management/{$opportunity->id}")->assertForbidden();

    // Same actor, same record: supervising ONE of its Offerte is enough, no
    // viewAll needed — the panel's own boundary, verbatim (D-3/D-9).
    Quote::factory()->for($opportunity)->create(['supervisor_id' => $actor->id]);

    $this->getJson("/api/activity-log/request-management/{$opportunity->id}")->assertOk();
});

it('aggregates notes (soft-deleted included), documents and the client anagraphic block', function () {
    $actor = requestManagementActivityUserWith(['viewAny', 'view', 'viewAll', 'viewActivity']);

    $registry = Registry::factory()->create();
    $card = PersonalData::factory()->for($registry, 'personable')->create();
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    // D-9: read access is re-keyed on the Opportunity's own Offerte — at
    // least one must exist, even with viewAll (see NoteAuthorizationTest's
    // own precedent for the same trap).
    Quote::factory()->for($opportunity)->create();

    $note = Note::factory()->for($opportunity, 'notable')->create();
    $note->delete();

    Attachment::factory()->for($opportunity, 'attachable')->create(['collection' => 'documents']);

    $card->update(['first_name' => 'Nuovo']);

    Sanctum::actingAs($actor);

    $modules = collect(
        $this->getJson("/api/activity-log/request-management/{$opportunity->id}")
            ->assertOk()
            ->json('data.items')
    )->pluck('module')->unique();

    expect($modules)->toContain($note->getMorphClass())
        ->toContain((new Attachment)->getMorphClass())
        ->toContain($card->getMorphClass());
});
