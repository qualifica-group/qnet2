<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/request-management/{quote} (spec 0049 data_contract, migrated
// onto the Quote by spec 0086, D-2; AC-030/031/032/040/041/042/043).

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
    function managedOpportunity(User $supervisor): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
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
    $quote = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [])->assertForbidden();
});

it('PATCH on a quote the actor does not supervise and without viewAll -> 403 (AC-032)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = Quote::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [])->assertForbidden();
});

it('PATCH on a nonexistent quote -> 404 (AC-032)', function () {
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
    $quote = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
        'attribute_values' => ['anything' => 'x'],
    ])->assertOk();

    // No column left to write to (D-1/D-2): a fresh read carries no trace.
    expect($quote->opportunity->fresh()->getAttributes())->not->toHaveKey('attribute_values');
});

// ---------------------------------------------------------------------------
// AC-043 — operative changes are recorded on the Opportunity's activity log
// (D-9: still anchored there even though the row moved onto the Quote)
// ---------------------------------------------------------------------------

it('PATCH next_callback_at writes an activity entry on the Opportunity (AC-043)', function () {
    $actor = requestManagementUpdaterWith(['update']);
    $quote = managedOpportunity($actor);
    $opportunity = $quote->opportunity;
    $callbackAt = now()->addDay()->format('Y-m-d\TH:i');
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$quote->id}", [
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
    $quote = managedOpportunity($actor);
    $opportunity = $quote->opportunity;
    Sanctum::actingAs($actor);

    $before = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    $this->patchJson("/api/request-management/{$quote->id}", [
        'general_notes' => $opportunity->general_notes,
    ])->assertOk();

    $after = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    expect($after)->toBe($before);
});
