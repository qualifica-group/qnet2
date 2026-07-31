<?php

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// "Prossimo richiamo" (spec 0052, D-1..D-5): a plain nullable datetime column
// on `opportunities`, written only via PATCH /api/request-management/{id},
// with an accompanying reminder-marker invariant (D-4) and its own operative
// table column (AC-006/AC-007).

uses(RefreshDatabase::class);

if (! function_exists('requestManagementUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
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
    function managedOpportunity(User $manager, array $attributes = []): Opportunity
    {
        $opportunity = Opportunity::factory()->create($attributes);
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — persistence, exact wire format, null clears, sparse leaves as-is
// ---------------------------------------------------------------------------

it('PATCH next_callback_at persists it and GET returns it in the exact "Y-m-d\TH:i" format (AC-002)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03T15:30',
    ])->assertOk()->assertJsonPath('data.next_callback_at', '2026-08-03T15:30');

    expect($opportunity->fresh()->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-08-03T15:30');

    $this->getJson("/api/request-management/{$opportunity->id}")
        ->assertOk()
        ->assertJsonPath('data.next_callback_at', '2026-08-03T15:30');
});

// User directive 2026-07-31: the hour is OPTIONAL. The frontend composes
// midnight itself, so the wire shape never changes — but the endpoint must
// accept a bare date too, or "no hour" would depend on the client formatting
// it right.
it('PATCH next_callback_at accepts a date with no time and stores it at midnight', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03',
    ])->assertOk()->assertJsonPath('data.next_callback_at', '2026-08-03T00:00');

    expect($opportunity->fresh()->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-08-03T00:00');
});

it('PATCH next_callback_at: null clears a previously stored value (AC-002)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, ['next_callback_at' => '2026-08-03 15:30:00']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => null,
    ])->assertOk()->assertJsonPath('data.next_callback_at', null);

    expect($opportunity->fresh()->next_callback_at)->toBeNull();
});

it('PATCH without the next_callback_at key leaves the persisted value untouched — sparse (AC-002)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, ['next_callback_at' => '2026-08-03 15:30:00']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [])->assertOk();

    expect($opportunity->fresh()->next_callback_at->format('Y-m-d\TH:i'))->toBe('2026-08-03T15:30');
});

// ---------------------------------------------------------------------------
// AC-003 — the reminder marker (D-4 invariant)
// ---------------------------------------------------------------------------

it('changing next_callback_at zeroes next_callback_reminded_at in the same save (AC-003)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, [
        'next_callback_at' => '2026-08-03 15:30:00',
        'next_callback_reminded_at' => '2026-08-01 09:00:00',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-05T10:00',
    ])->assertOk();

    expect($opportunity->fresh()->next_callback_reminded_at)->toBeNull();
});

it('resubmitting the SAME next_callback_at value does NOT zero next_callback_reminded_at (AC-003)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, [
        'next_callback_at' => '2026-08-03 15:30:00',
        'next_callback_reminded_at' => '2026-08-01 09:00:00',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03T15:30',
    ])->assertOk();

    expect($opportunity->fresh()->next_callback_reminded_at?->format('Y-m-d H:i:s'))->toBe('2026-08-01 09:00:00');
});

// ---------------------------------------------------------------------------
// AC-004 — explicit activity entry, only on real change; the marker is never
// audited (it is a technical bookkeeping column, not a domain value, D-3)
// ---------------------------------------------------------------------------

it('a next_callback_at change writes an activity entry with attributes/old (AC-004)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, ['next_callback_at' => '2026-08-03 15:30:00']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-05T10:00',
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toMatchArray(['next_callback_at' => '2026-08-05T10:00']);
    expect($activity->properties->get('old'))->toMatchArray(['next_callback_at' => '2026-08-03T15:30']);
    expect($activity->properties->get('attributes'))->not->toHaveKey('next_callback_reminded_at');
    expect($activity->properties->get('old'))->not->toHaveKey('next_callback_reminded_at');
});

it('resubmitting the SAME next_callback_at value writes no activity entry', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor, ['next_callback_at' => '2026-08-03 15:30:00']);
    Sanctum::actingAs($actor);

    $before = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03T15:30',
    ])->assertOk();

    $after = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->count();

    expect($after)->toBe($before);
});

// ---------------------------------------------------------------------------
// AC-005 — validation + authorization
// ---------------------------------------------------------------------------

it('PATCH with an unparsable next_callback_at -> 422 and no write (AC-005)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => 'non-una-data',
    ])->assertStatus(422)->assertJsonValidationErrors('next_callback_at');

    expect($opportunity->fresh()->next_callback_at)->toBeNull();
});

it('PATCH next_callback_at without request-management.update -> 403 (AC-005)', function () {
    $actor = requestManagementUserWith(['view']);
    $opportunity = managedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03T15:30',
    ])->assertForbidden();
});

it('PATCH next_callback_at on an opportunity outside GA2 scope, without viewAll -> 403 (AC-005)', function () {
    $actor = requestManagementUserWith(['view', 'update']);
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'next_callback_at' => '2026-08-03T15:30',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-006 — the column catalogue and the row projection
// ---------------------------------------------------------------------------

it('columns() exposes next_callback_at as visible/sortable/filterable datetime+date (AC-006)', function () {
    $actor = requestManagementUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'));
    $column = $columns->firstWhere('id', 'next_callback_at');

    expect($column)->not->toBeNull()
        ->and($column['type'])->toBe('datetime')
        ->and($column['filterType'])->toBe('date')
        ->and($column['visible'])->toBeTrue()
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterable'])->toBeTrue();
});

it('rows: next_callback_at is projected on every row in the same wire format as the panel (AC-006)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $withCallback = Opportunity::factory()->create(['next_callback_at' => '2026-08-03 15:30:00']);
    $withoutCallback = Opportunity::factory()->create(['next_callback_at' => null]);
    Sanctum::actingAs($actor);

    $items = collect($this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($items->firstWhere('id', $withCallback->id)['next_callback_at'])->toBe('2026-08-03T15:30');
    expect($items->firstWhere('id', $withoutCallback->id)['next_callback_at'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-007 — sort + column date filter + advanced date-range filter
// ---------------------------------------------------------------------------

it('rows: sorting by next_callback_at orders rows in both directions (AC-007)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $earlier = Opportunity::factory()->create(['next_callback_at' => '2026-08-01 09:00:00']);
    $later = Opportunity::factory()->create(['next_callback_at' => '2026-08-10 09:00:00']);
    Sanctum::actingAs($actor);

    $asc = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'next_callback_at', 'sort' => 'asc']],
    ])->assertOk();
    expect(collect($asc->json('items'))->pluck('id')->all())->toBe([$earlier->id, $later->id]);

    $desc = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'next_callback_at', 'sort' => 'desc']],
    ])->assertOk();
    expect(collect($desc->json('items'))->pluck('id')->all())->toBe([$later->id, $earlier->id]);
});

it('rows: the next_callback_at column date filter (inRange) narrows to the interval (AC-007)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $inRange = Opportunity::factory()->create(['next_callback_at' => '2026-08-03 10:00:00']);
    $outOfRange = Opportunity::factory()->create(['next_callback_at' => '2026-09-01 10:00:00']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'next_callback_at' => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-05'],
        ],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$inRange->id])->and($ids)->not->toContain($outOfRange->id);
});

it('rows: the next_callback_range advanced filter narrows to {from, to} (AC-007)', function () {
    $actor = requestManagementUserWith(['viewAny', 'viewAll']);
    $inRange = Opportunity::factory()->create(['next_callback_at' => '2026-08-03 10:00:00']);
    $outOfRange = Opportunity::factory()->create(['next_callback_at' => '2026-09-01 10:00:00']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'advancedFilters' => ['next_callback_range' => ['from' => '2026-08-01', 'to' => '2026-08-05']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$inRange->id])->and($ids)->not->toContain($outOfRange->id);
});
