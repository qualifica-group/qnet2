<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-10-22 09:00:00'));

afterEach(fn () => Carbon::setTestNow());

it('0304 AC-007: a bulk approve decides the valid ones and reports the others', function () {
    $approver = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $approver);
    $ok = absenceSubmitted($person, $approver);
    $done = absenceSubmitted(absenceUserWith(), $approver, ['status' => 'approved']);
    $hidden = absenceSubmitted(absenceUserWith(), absenceUserWith());
    Sanctum::actingAs($approver);

    $response = $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'approve', 'ids' => [$ok->id, $done->id, $hidden->id]])->assertOk();

    expect($response->json('data.decided'))->toBe([$ok->id])
        ->and(collect($response->json('data.failed'))->pluck('id')->all())->toBe([$done->id, $hidden->id])
        ->and($response->json('data.failed.1.message'))->toBe(__('absence_requests.bulk_not_found'))
        ->and($response->json('data.failed.0.message'))->toBe(__('absence_requests.not_submitted'))
        ->and($ok->fresh()->status->value)->toBe('approved')
        ->and($hidden->fresh()->status->value)->toBe('submitted')
        ->and(DB::table('activity_log')->where('subject_type', 'absence_request')->where('subject_id', $ok->id)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('notifiable_id', $person->id)->exists())->toBeTrue();
});

it('0304 AC-007: a visible request the actor may not decide is a failed entry with the 403 message', function () {
    $actor = absenceUserWith();
    $person = absenceUserWith();
    absenceProfile($person, $actor);
    $own = absenceSubmitted($actor, absenceUserWith());
    Sanctum::actingAs($actor);

    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'approve', 'ids' => [$own->id]])
        ->assertOk()->assertJsonPath('data.decided', [])->assertJsonPath('data.failed.0.id', $own->id);
    expect($own->fresh()->status->value)->toBe('submitted');
});

it('0304 AC-007: a bulk reject needs a reason and stores it', function () {
    $approver = absenceUserWith();
    $request = absenceSubmitted(absenceUserWith(), $approver);
    Sanctum::actingAs($approver);

    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'reject', 'ids' => [$request->id]])
        ->assertStatus(422)->assertJsonValidationErrors('rejection_reason');
    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'reject', 'ids' => [$request->id], 'rejection_reason' => 'No cover'])
        ->assertOk()->assertJsonPath('data.decided', [$request->id]);

    expect($request->fresh()->rejection_reason)->toBe('No cover');
});

it('0304 AC-007: validation (action, ids range, distinct) and authorization (viewAny)', function () {
    Sanctum::actingAs(absenceUserWith());

    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'nope', 'ids' => []])->assertStatus(422)->assertJsonValidationErrors(['action', 'ids']);
    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'approve', 'ids' => range(1, 101)])->assertStatus(422)->assertJsonValidationErrors('ids');
    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'approve', 'ids' => [1, 1]])->assertStatus(422)->assertJsonValidationErrors('ids.0');

    Sanctum::actingAs(absenceUserWith([]));
    $this->postJson('/api/absence-requests/bulk-decision', ['action' => 'approve', 'ids' => [1]])->assertForbidden();
});
