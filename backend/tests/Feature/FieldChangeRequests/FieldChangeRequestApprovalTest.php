<?php

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/field-change-requests/{id}/approve|reject (spec 0078): AC-028..035.

uses(RefreshDatabase::class);

if (! function_exists('fcrHandlerEnsurePermissions')) {
    function fcrHandlerEnsurePermissions(): void
    {
        foreach (['viewAny', 'view', 'create', 'manage', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("field-change-requests.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'viewAll', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
    }
}

if (! function_exists('fcrHandlerWith')) {
    /**
     * @param  array<int, string>  $fieldChangeAbilities
     * @param  array<int, string>  $requestManagementAbilities
     */
    function fcrHandlerWith(array $fieldChangeAbilities, array $requestManagementAbilities): User
    {
        fcrHandlerEnsurePermissions();

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

if (! function_exists('fcrPendingRequest')) {
    /**
     * @return array{0: Opportunity, 1: FieldChangeRequest, 2: Source}
     */
    function fcrPendingRequest(): array
    {
        $currentSource = Source::factory()->create();
        $requestedSource = Source::factory()->create();
        $opportunity = Opportunity::factory()->create(['source_id' => $currentSource->id]);

        $fieldChangeRequest = FieldChangeRequest::factory()->create([
            'resource' => 'request-management',
            'subject_type' => 'opportunity',
            'subject_id' => $opportunity->id,
            'field' => 'source_id',
            'current_value' => $currentSource->id,
            'requested_value' => $requestedSource->id,
            'status' => 'pending',
            'pending_key' => "opportunity:{$opportunity->id}:source_id",
        ]);

        return [$opportunity, $fieldChangeRequest, $requestedSource];
    }
}

// ---------------------------------------------------------------------------
// AC-028 / AC-029 — happy path approve/reject
// ---------------------------------------------------------------------------

it('AC-028: approve applies the value, closes the request (200)', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$opportunity, $fieldChangeRequest, $requestedSource] = fcrPendingRequest();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('message', 'Change request approved')
        ->assertJsonPath('data.status', 'approved');

    expect($opportunity->fresh()->source_id)->toBe($requestedSource->id);

    $fieldChangeRequest->refresh();
    expect($fieldChangeRequest->status->value)->toBe('approved')
        ->and($fieldChangeRequest->handled_by_id)->toBe($manager->id)
        ->and($fieldChangeRequest->handled_at)->not->toBeNull()
        ->and($fieldChangeRequest->pending_key)->toBeNull();
});

it('AC-029: reject closes the request WITHOUT touching the record (200)', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$opportunity, $fieldChangeRequest] = fcrPendingRequest();
    $originalSourceId = $opportunity->source_id;
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($opportunity->fresh()->source_id)->toBe($originalSourceId);

    $fieldChangeRequest->refresh();
    expect($fieldChangeRequest->status->value)->toBe('rejected')
        ->and($fieldChangeRequest->pending_key)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-030 — conflict: value changed since the request was proposed
// ---------------------------------------------------------------------------

it('AC-030: the field changed since proposal -> 409, no write, request stays pending', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$opportunity, $fieldChangeRequest] = fcrPendingRequest();
    $driftedSource = Source::factory()->create();
    $opportunity->forceFill(['source_id' => $driftedSource->id])->save();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")
        ->assertStatus(409);

    expect($opportunity->fresh()->source_id)->toBe($driftedSource->id);
    expect($fieldChangeRequest->fresh()->status->value)->toBe('pending');
});

// ---------------------------------------------------------------------------
// AC-031 — already handled -> 422, nothing changes
// ---------------------------------------------------------------------------

it('AC-031: approve on an already-approved request -> 422', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    $fieldChangeRequest->forceFill(['status' => 'approved', 'pending_key' => null])->save();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertStatus(422);
});

it('AC-031: reject on an already-rejected request -> 422', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    $fieldChangeRequest->forceFill(['status' => 'rejected', 'pending_key' => null])->save();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/reject")->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-032 — missing field-change-requests.manage -> 403, even for the requester
// ---------------------------------------------------------------------------

it('AC-032: approve without field-change-requests.manage -> 403', function () {
    $actor = fcrHandlerWith([], ['view', 'viewAll', 'update', 'updateSource']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    Sanctum::actingAs($actor);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertForbidden();
    expect($fieldChangeRequest->fresh()->status->value)->toBe('pending');
});

it('AC-032: the requester themself cannot approve without manage -> 403', function () {
    $requester = fcrHandlerWith(['create'], ['view', 'viewAll']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    $fieldChangeRequest->update(['requested_by_id' => $requester->id]);
    Sanctum::actingAs($requester);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-033 — manage but no write permission on the field itself -> 403, no write
// ---------------------------------------------------------------------------

it('AC-033: manage WITHOUT request-management.updateSource -> 403, value not applied', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update']);
    [$opportunity, $fieldChangeRequest] = fcrPendingRequest();
    $originalSourceId = $opportunity->source_id;
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertForbidden();

    expect($opportunity->fresh()->source_id)->toBe($originalSourceId);
    expect($fieldChangeRequest->fresh()->status->value)->toBe('pending');
});

// ---------------------------------------------------------------------------
// AC-034 — handling_note persisted, or null when omitted
// ---------------------------------------------------------------------------

it('AC-034: a note passed to approve is persisted as handling_note', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve", ['note' => 'Ok, confermato.'])
        ->assertOk()
        ->assertJsonPath('data.handling_note', 'Ok, confermato.');

    expect($fieldChangeRequest->fresh()->handling_note)->toBe('Ok, confermato.');
});

it('AC-034: no note passed -> handling_note stays null', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [, $fieldChangeRequest] = fcrPendingRequest();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.handling_note', null);

    expect($fieldChangeRequest->fresh()->handling_note)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-035 — the requested value became invalid (e.g. Fonte deleted) -> 422, no partial state
// ---------------------------------------------------------------------------

it('AC-035: the requested Fonte was deleted meanwhile -> 422, request stays pending, record untouched', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    $currentSource = Source::factory()->create();
    $requestedSource = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $currentSource->id]);

    $fieldChangeRequest = FieldChangeRequest::factory()->create([
        'resource' => 'request-management',
        'subject_type' => 'opportunity',
        'subject_id' => $opportunity->id,
        'field' => 'source_id',
        'current_value' => $currentSource->id,
        'requested_value' => $requestedSource->id,
        'status' => 'pending',
        'pending_key' => "opportunity:{$opportunity->id}:source_id",
    ]);
    $requestedSource->delete();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertStatus(422);

    expect($opportunity->fresh()->source_id)->toBe($currentSource->id);
    expect($fieldChangeRequest->fresh()->status->value)->toBe('pending');
});
