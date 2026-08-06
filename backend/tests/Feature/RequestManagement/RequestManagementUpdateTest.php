<?php

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/request-management/{opportunity} (spec 0049 data_contract,
// AC-030/031/032/040/041/042/043).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUpdaterWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUpdaterWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('managedOpportunity')) {
    function managedOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-030/031 (spec 0049) are GONE: spec 0083, D-2 removed the Opportunity's
// own working-state field entirely — the PATCH payload no longer accepts a
// working-state override at all. That rule now lives on the Offerta
// (tests/Feature/Quotes/QuoteRequiresNoteTest.php, AC-021/023..026).
//
// AC-032 — authz + scope + 404
// ---------------------------------------------------------------------------

it('PATCH without request-management.update -> 403 (AC-032)', function () {
    $actor = requestManagementUpdaterWith([]);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertForbidden();
});

it('PATCH on an opportunity the actor does not manage and without viewAll -> 403 (AC-032)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertForbidden();
});

it('PATCH on a nonexistent opportunity -> 404 (AC-032)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/request-management/999999', [])->assertNotFound();
});

// ---------------------------------------------------------------------------
// Spec 0084, D-1/AC-042: the dynamic "Informazioni aggiuntive" write pipeline
// (formerly AC-040/041/042) moved to the Offerta (Quote) — see
// tests/Feature/Quotes/QuoteAttributeValuesTest.php. `attribute_values` is no
// longer an accepted key on this PATCH; a submitted one is silently dropped.
// ---------------------------------------------------------------------------

it('PATCH with an attribute_values payload produces no write (AC-042)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'attribute_values' => ['anything' => 'x'],
    ])->assertOk();

    // No column left to write to (D-1/D-2): a fresh read carries no trace.
    expect($opportunity->fresh()->getAttributes())->not->toHaveKey('attribute_values');
});

// ---------------------------------------------------------------------------
// AC-043 — operative changes are recorded on the Opportunity's activity log
// ---------------------------------------------------------------------------

it('PATCH next_callback_at writes an activity entry on the Opportunity (AC-043)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = managedOpportunity($actor);
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => $callbackAt,
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBe($actor->id);
    expect($activity->properties->get('attributes'))->toHaveKey('next_callback_at');

    // The module exposes no separately-gated activity endpoint (lead
    // decision): the generic ActivityLogController resolves its Policy by
    // MODEL CLASS, so a `request-management` resource key would have been
    // gated by `opportunities.*`, never `request-management.viewActivity` —
    // removed from config/activity-log.php. This write-side entry is
    // readable through the Opportunity's OWN resource key instead; see
    // RequestManagementActivityTest for the dedicated coverage.
});

it('PATCH with no actual change writes no activity entry', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $before = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'general_notes' => $opportunity->general_notes,
    ])->assertOk();

    $after = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    expect($after)->toBe($before);
});
