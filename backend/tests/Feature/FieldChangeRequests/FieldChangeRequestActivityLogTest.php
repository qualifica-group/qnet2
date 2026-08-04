<?php

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// Activity log (spec 0078): AC-041/042.

uses(RefreshDatabase::class);

if (! function_exists('fcrActivityEnsurePermissions')) {
    function fcrActivityEnsurePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
    }
}

if (! function_exists('fcrActivityActorWith')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $requestManagementAbilities
     */
    function fcrActivityActorWith(array $fieldChangeAbilities, array $requestManagementAbilities = ['view', 'viewAll']): User
    {
        fcrActivityEnsurePermissions();

        $user = User::factory()->create();

        foreach ($fieldChangeAbilities as $ability) {
            $user->givePermissionTo("field-change-requests.{$ability}");
        }

        foreach ($requestManagementAbilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-041 — three distinct events + the standard Opportunity update entry
// ---------------------------------------------------------------------------

it('AC-041: creation, approval and rejection each write a distinct, caused activity-log entry', function () {
    $requester = fcrActivityActorWith(['create']);
    $manager = fcrActivityActorWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($requester);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();

    $fieldChangeRequest = FieldChangeRequest::sole();

    $created = Activity::query()
        ->where('subject_type', $fieldChangeRequest->getMorphClass())
        ->where('subject_id', $fieldChangeRequest->id)
        ->where('event', 'field_change_request.created')
        ->sole();
    expect($created->causer_id)->toBe($requester->id);

    Sanctum::actingAs($manager);
    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertOk();

    $approved = Activity::query()
        ->where('subject_type', $fieldChangeRequest->getMorphClass())
        ->where('subject_id', $fieldChangeRequest->id)
        ->where('event', 'field_change_request.approved')
        ->sole();
    expect($approved->causer_id)->toBe($manager->id);

    $opportunityUpdate = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
    expect($opportunityUpdate)->not->toBeNull();
    expect($opportunityUpdate->properties->get('attributes'))->toMatchArray(['source_id' => $newSource->id]);

    // A second, independent request+reject to cover the `.rejected` event.
    $anotherSource = Source::factory()->create();
    Sanctum::actingAs($requester);
    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => $anotherSource->id,
    ])->assertCreated();
    $second = FieldChangeRequest::where('status', 'pending')->sole();

    Sanctum::actingAs($manager);
    $this->postJson("/api/field-change-requests/{$second->id}/reject")->assertOk();

    $rejected = Activity::query()
        ->where('subject_type', $second->getMorphClass())
        ->where('subject_id', $second->id)
        ->where('event', 'field_change_request.rejected')
        ->sole();
    expect($rejected->causer_id)->toBe($manager->id);
});

// ---------------------------------------------------------------------------
// AC-042 — GET /api/activity-log/field-change-requests/{id}
// ---------------------------------------------------------------------------

it('AC-042: an actor with viewActivity + view reads the aggregated activity log (200)', function () {
    $requester = fcrActivityActorWith(['create', 'view', 'viewActivity']);
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($requester);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();
    $fieldChangeRequest = FieldChangeRequest::sole();

    $this->getJson("/api/activity-log/field-change-requests/{$fieldChangeRequest->id}")->assertOk();
});

it('AC-042: an actor without viewActivity -> 403', function () {
    $requester = fcrActivityActorWith(['create', 'view']);
    $opportunity = Opportunity::factory()->create(['source_id' => Source::factory()->create()->id]);
    $newSource = Source::factory()->create();
    Sanctum::actingAs($requester);

    $this->postJson('/api/field-change-requests', [
        'resource' => 'request-management',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'requested_value' => $newSource->id,
    ])->assertCreated();
    $fieldChangeRequest = FieldChangeRequest::sole();

    $this->getJson("/api/activity-log/field-change-requests/{$fieldChangeRequest->id}")->assertForbidden();
});
