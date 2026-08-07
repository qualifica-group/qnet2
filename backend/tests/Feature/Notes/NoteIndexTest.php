<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// D-13: GET /api/notes returns only ROOT notes (created_at desc, tie-break id
// desc), each with its full `replies` (created_at asc); keyset pagination
// counts roots and pages through them without duplicates or gaps (AC-040).

uses(RefreshDatabase::class);

if (! function_exists('noteActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function noteActor(array $abilities = []): User
    {
        foreach (['request-management.view', 'request-management.viewAll', 'notes.create'] as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo($ability);
        }

        return $user;
    }
}

if (! function_exists('noteManagedOpportunity')) {
    // Spec 0086, D-9: read/mention access is re-keyed on the Opportunity's
    // own Offerte (D-3, `quotes.supervisor_id`), not the GA2 pivot slot.
    function noteManagedOpportunity(User $supervisor): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        Quote::factory()->for($opportunity)->create(['supervisor_id' => $supervisor->id]);

        return $opportunity;
    }
}

it('index: only roots (created_at desc/id desc), each with its full asc replies; keyset pages exactly (AC-040)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $t0 = now()->subHour();

    $rootA = Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $opportunity->id, 'created_at' => $t0]);
    $rootB = Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $opportunity->id, 'created_at' => $t0->copy()->addMinute()]);
    $rootC = Note::factory()->create(['notable_type' => 'opportunity', 'notable_id' => $opportunity->id, 'created_at' => $t0->copy()->addMinutes(2)]);

    $replyOld = Note::factory()->create([
        'notable_type' => 'opportunity', 'notable_id' => $opportunity->id,
        'parent_id' => $rootB->id, 'created_at' => $t0->copy()->addMinutes(3),
    ]);
    $replyNew = Note::factory()->create([
        'notable_type' => 'opportunity', 'notable_id' => $opportunity->id,
        'parent_id' => $rootB->id, 'created_at' => $t0->copy()->addMinutes(4),
    ]);

    // Page 1 (limit=2): the two newest roots (C, B) — never $replyOld/$replyNew at the top level.
    $page1 = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&limit=2")->assertOk();

    expect(collect($page1->json('data'))->pluck('id')->all())->toBe([$rootC->id, $rootB->id]);
    expect($page1->json('meta.has_more'))->toBeTrue();
    expect($page1->json('meta.next_cursor'))->not->toBeNull();

    $rootBPayload = collect($page1->json('data'))->firstWhere('id', $rootB->id);
    expect(collect($rootBPayload['replies'])->pluck('id')->all())->toBe([$replyOld->id, $replyNew->id]);

    $rootCPayload = collect($page1->json('data'))->firstWhere('id', $rootC->id);
    expect($rootCPayload['replies'])->toBe([]);

    // Page 2: the remaining root (A), has_more false, no duplicates/gaps.
    $cursor = $page1->json('meta.next_cursor');
    $page2 = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&limit=2&cursor={$cursor}")->assertOk();

    expect(collect($page2->json('data'))->pluck('id')->all())->toBe([$rootA->id]);
    expect($page2->json('meta.has_more'))->toBeFalse();
    expect($page2->json('meta.next_cursor'))->toBeNull();
});

it('keyset pagination ties correctly on IDENTICAL created_at (tie-break by id desc), including a page boundary exactly at the tie group\'s edge (AC-040)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    // Forced explicitly identical created_at across 4 roots — not relying
    // on execution speed to produce (or avoid) a collision, since a close
    // insert or low timestamp resolution makes this a real, not a lab-only,
    // scenario. Created in ascending id order (rootTied1 < ... < rootTied4).
    $tiedAt = now()->subHour();
    $tiedRoots = collect(range(1, 4))->map(fn () => Note::factory()->create([
        'notable_type' => 'opportunity',
        'notable_id' => $opportunity->id,
        'created_at' => $tiedAt,
    ]));

    // A strictly OLDER, non-tied root: the page boundary that lands exactly
    // on the last (smallest-id) member of the tie group must move cleanly
    // into this one next, with no duplicate and no gap.
    $olderRoot = Note::factory()->create([
        'notable_type' => 'opportunity',
        'notable_id' => $opportunity->id,
        'created_at' => $tiedAt->copy()->subMinute(),
    ]);

    // Expected order: the tie group by id DESC, then the older root.
    $expectedOrder = $tiedRoots->sortByDesc('id')->pluck('id')->values()->push($olderRoot->id)->all();

    $seenIds = [];
    $cursor = null;
    $hasMore = true;
    $pageCount = 0;

    while ($hasMore) {
        $pageCount++;
        expect($pageCount)->toBeLessThanOrEqual(10); // safety valve against an infinite-loop regression

        $query = "entity_type=request-management&entity_id={$opportunity->id}&limit=2";
        $response = $this->getJson('/api/notes?'.$query.($cursor !== null ? "&cursor={$cursor}" : ''))->assertOk();

        $pageIds = collect($response->json('data'))->pluck('id')->all();

        expect(array_intersect($pageIds, $seenIds))->toBe([]); // no duplicate across pages
        $seenIds = array_merge($seenIds, $pageIds);

        $hasMore = $response->json('meta.has_more');
        $cursor = $response->json('meta.next_cursor');
        expect($hasMore)->toBe($cursor !== null); // has_more/next_cursor always agree
    }

    // No gaps either: the full, deduplicated union equals the expected order exactly.
    expect($seenIds)->toBe($expectedOrder);
});

it('a malformed cursor -> 422 (data_contract)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&cursor=not-base64!!")
        ->assertStatus(422)
        ->assertJsonValidationErrors('cursor');
});
