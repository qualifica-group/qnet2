<?php

use App\Models\FieldChangeRequest;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// POST /api/field-change-requests/{id}/approve|reject (spec 0078): AC-028..035.
// Spec 0086, D-10 (corrected in execution): the subject of a request-management
// field change request is now the QUOTE (`subject_type = 'quote'`), even
// though `source_id` itself is still resolved/applied through
// `quote.opportunity` — approval runs `updateCell()` on
// `RequestManagementTableDefinition`, whose `baseQuery()`/`modelClass()` are
// Quote-rooted.

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
     * @return array{0: Quote, 1: FieldChangeRequest, 2: Source}
     */
    function fcrPendingRequest(): array
    {
        $currentSource = Source::factory()->create();
        $requestedSource = Source::factory()->create();
        $opportunity = Opportunity::factory()->create(['source_id' => $currentSource->id]);
        $quote = Quote::factory()->for($opportunity)->create();

        $fieldChangeRequest = FieldChangeRequest::factory()->create([
            'resource' => 'request-management',
            'subject_type' => 'quote',
            'subject_id' => $quote->id,
            'field' => 'source_id',
            'current_value' => $currentSource->id,
            'requested_value' => $requestedSource->id,
            'status' => 'pending',
            'pending_key' => "quote:{$quote->id}:source_id",
        ]);

        return [$quote, $fieldChangeRequest, $requestedSource];
    }
}

// ---------------------------------------------------------------------------
// AC-028 / AC-029 — happy path approve/reject
// ---------------------------------------------------------------------------

it('AC-028: approve applies the value, closes the request (200)', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$quote, $fieldChangeRequest, $requestedSource] = fcrPendingRequest();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('message', 'Change request approved')
        ->assertJsonPath('data.status', 'approved');

    expect($quote->opportunity->fresh()->source_id)->toBe($requestedSource->id);

    $fieldChangeRequest->refresh();
    expect($fieldChangeRequest->status->value)->toBe('approved')
        ->and($fieldChangeRequest->handled_by_id)->toBe($manager->id)
        ->and($fieldChangeRequest->handled_at)->not->toBeNull()
        ->and($fieldChangeRequest->pending_key)->toBeNull();
});

// spec 0086, AC-043: the underlying `source_id` lives on the shared
// Opportunity, so approving one offer's change request also moves its
// SIBLING offer's Fonte — but `pending_change_requests` stays per-Quote
// (D-10 corrected), so the sibling's own still-open request is untouched.
it('AC-043: approval reflects on the SIBLING offer, whose own pending_change_requests badge stays independent', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource', 'viewAny']);
    [$quote, $fieldChangeRequest, $requestedSource] = fcrPendingRequest();
    $sibling = Quote::factory()->for($quote->opportunity)->create();
    $siblingRequest = FieldChangeRequest::factory()->create([
        'resource' => 'request-management',
        'subject_type' => 'quote',
        'subject_id' => $sibling->id,
        'field' => 'source_id',
        'current_value' => $quote->opportunity->source_id,
        'requested_value' => Source::factory()->create()->id,
        'status' => 'pending',
        'pending_key' => "quote:{$sibling->id}:source_id",
    ]);
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertOk();

    // The Fonte is shared through the Opportunity: the sibling reflects it too.
    expect($sibling->opportunity->fresh()->source_id)->toBe($requestedSource->id);
    // But the sibling's OWN pending request is untouched by this approval.
    expect($siblingRequest->fresh()->status->value)->toBe('pending');

    $rows = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));
    expect($rows->firstWhere('id', $quote->id)['pending_change_requests'])->toBe(0)
        ->and($rows->firstWhere('id', $sibling->id)['pending_change_requests'])->toBe(1);
});

it('AC-029: reject closes the request WITHOUT touching the record (200)', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$quote, $fieldChangeRequest] = fcrPendingRequest();
    $originalSourceId = $quote->opportunity->source_id;
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($quote->opportunity->fresh()->source_id)->toBe($originalSourceId);

    $fieldChangeRequest->refresh();
    expect($fieldChangeRequest->status->value)->toBe('rejected')
        ->and($fieldChangeRequest->pending_key)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-030 — conflict: value changed since the request was proposed
// ---------------------------------------------------------------------------

it('AC-030: the field changed since proposal -> 409, no write, request stays pending', function () {
    $manager = fcrHandlerWith(['manage'], ['view', 'viewAll', 'update', 'updateSource']);
    [$quote, $fieldChangeRequest] = fcrPendingRequest();
    $driftedSource = Source::factory()->create();
    $quote->opportunity->forceFill(['source_id' => $driftedSource->id])->save();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")
        ->assertStatus(409);

    expect($quote->opportunity->fresh()->source_id)->toBe($driftedSource->id);
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
    [$quote, $fieldChangeRequest] = fcrPendingRequest();
    $originalSourceId = $quote->opportunity->source_id;
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertForbidden();

    expect($quote->opportunity->fresh()->source_id)->toBe($originalSourceId);
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
    $quote = Quote::factory()->for($opportunity)->create();

    $fieldChangeRequest = FieldChangeRequest::factory()->create([
        'resource' => 'request-management',
        'subject_type' => 'quote',
        'subject_id' => $quote->id,
        'field' => 'source_id',
        'current_value' => $currentSource->id,
        'requested_value' => $requestedSource->id,
        'status' => 'pending',
        'pending_key' => "quote:{$quote->id}:source_id",
    ]);
    $requestedSource->delete();
    Sanctum::actingAs($manager);

    $this->postJson("/api/field-change-requests/{$fieldChangeRequest->id}/approve")->assertStatus(422);

    expect($quote->opportunity->fresh()->source_id)->toBe($currentSource->id);
    expect($fieldChangeRequest->fresh()->status->value)->toBe('pending');
});
