<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/request-management/rows/{row} — inline edit of
// `next_callback_at` (spec 0054, D-4): the ONE request-management column
// activated this round besides the workflow status (paused pending a
// product decision on `requires_note`, see docs/HANDOFF.md). Spec 0086,
// D-1: the row is now a `quotes` record; `next_callback_at` stays on the
// Opportunity, reached through `quote.opportunity`.

uses(RefreshDatabase::class);

if (! function_exists('requestManagementInlineEditActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementInlineEditActor(array $abilities): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('managedOpportunityForInlineEdit')) {
    function managedOpportunityForInlineEdit(User $supervisor): Quote
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$supervisor->id => ['position' => 2]]);

        return Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);
    }
}

it('GET /api/tables/request-management/columns: next_callback_at emits editable:true', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['next_callback_at']['editable'])->toBeTrue();
});

it('AC-013: PATCH next_callback_at -> 200, persisted, and the reminder marker clears when the value CHANGES', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    $quote = managedOpportunityForInlineEdit($actor);
    $quote->opportunity->forceFill(['next_callback_reminded_at' => now()])->save();
    Sanctum::actingAs($actor);

    $newCallback = now()->addDays(3)->format('Y-m-d\TH:i');

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => $newCallback,
    ])->assertOk();

    $fresh = $quote->opportunity->fresh();
    expect($fresh->next_callback_reminded_at)->toBeNull();
    expect($fresh->next_callback_at->format('Y-m-d\TH:i'))->toBe($newCallback);
});

it('AC-013: resending the SAME next_callback_at value leaves the reminder marker untouched', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    $quote = managedOpportunityForInlineEdit($actor);
    $opportunity = $quote->opportunity;
    $opportunity->next_callback_at = now()->addDays(2);
    $opportunity->next_callback_reminded_at = now();
    $opportunity->save();
    Sanctum::actingAs($actor);

    $unchanged = $opportunity->fresh()->next_callback_at->format('Y-m-d\TH:i');

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => $unchanged,
    ])->assertOk();

    expect($opportunity->fresh()->next_callback_reminded_at)->not->toBeNull();
});

it('value:null clears next_callback_at -> 200, NULL persisted', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    $quote = managedOpportunityForInlineEdit($actor);
    $quote->opportunity->forceFill(['next_callback_at' => now()->addDay()])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => null,
    ])->assertOk();

    expect($quote->opportunity->fresh()->next_callback_at)->toBeNull();
});

it('AC-014: the write goes through RequestManagementService::updateWork (activity-log entry exists)', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    $quote = managedOpportunityForInlineEdit($actor);
    $opportunity = $quote->opportunity;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => now()->addDay()->format('Y-m-d\TH:i'),
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toHaveKey('next_callback_at');
});

it('AC-015: an operator not managing the record and without viewAll receives 404 on next_callback_at PATCH', function () {
    $actor = requestManagementInlineEditActor(['viewAny', 'update']);
    $quote = Quote::factory()->create();
    $otherManager = User::factory()->create();
    $quote->opportunity->managers()->sync([$otherManager->id => ['position' => 2]]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'next_callback_at',
        'value' => now()->addDay()->format('Y-m-d\TH:i'),
    ])->assertNotFound();
});
