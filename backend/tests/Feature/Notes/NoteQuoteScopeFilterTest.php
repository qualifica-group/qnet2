<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0085, D-2: `quote_scope` on GET /api/notes (AC-010..017) — narrows
// ROOT notes only, applied in QUERY (never lightly filtered after the
// fetch), composes with the existing keyset pagination.

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
    function noteManagedOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => Opportunity::OPERATOR_MANAGER_POSITION]]);

        return $opportunity;
    }
}

if (! function_exists('rootNote')) {
    function rootNote(Opportunity $opportunity, ?int $quoteId, $createdAt): Note
    {
        $note = Note::factory()->create([
            'notable_type' => 'opportunity',
            'notable_id' => $opportunity->id,
            'created_at' => $createdAt,
        ]);

        if ($quoteId !== null) {
            $note->forceFill(['quote_id' => $quoteId])->save();
        }

        return $note;
    }
}

it('quote_scope=all (the default) returns every root regardless of quote_id, newest first (AC-010)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $t0 = now()->subHour();
    $general1 = rootNote($opportunity, null, $t0);
    $general2 = rootNote($opportunity, null, $t0->copy()->addMinute());
    $onA = rootNote($opportunity, $quoteA->id, $t0->copy()->addMinutes(2));
    $onB1 = rootNote($opportunity, $quoteB->id, $t0->copy()->addMinutes(3));
    $onB2 = rootNote($opportunity, $quoteB->id, $t0->copy()->addMinutes(4));

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&limit=10")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$onB2->id, $onB1->id, $onA->id, $general2->id, $general1->id]);
});

it('quote_scope=general returns only the general roots (AC-011)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $general = rootNote($opportunity, null, now());
    rootNote($opportunity, $quoteA->id, now());

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope=general")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$general->id]);
});

it('quote_scope=<id offerta A> returns only the notes of A (AC-012)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $onA = rootNote($opportunity, $quoteA->id, now());
    rootNote($opportunity, $quoteB->id, now());
    rootNote($opportunity, null, now());

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope={$quoteA->id}")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$onA->id]);
});

it('quote_scope numeric referring to an offer of ANOTHER opportunity -> 422 (AC-013)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $otherOpportunity = Opportunity::factory()->create();
    $foreignQuote = Quote::factory()->create(['opportunity_id' => $otherOpportunity->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope={$foreignQuote->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote_scope');
});

it('quote_scope=pippo -> 422 (AC-014)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope=pippo")
        ->assertStatus(422)
        ->assertJsonValidationErrors('quote_scope');
});

it('paginating a filtered list keeps the filter applied: no duplicates, no gaps (AC-015)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $t0 = now()->subHour();
    $onA = collect(range(1, 4))->map(fn (int $i) => rootNote($opportunity, $quoteA->id, $t0->copy()->addMinutes($i)));
    rootNote($opportunity, $quoteB->id, $t0->copy()->addMinutes(10)); // must never leak into the A-scoped pages
    rootNote($opportunity, null, $t0->copy()->addMinutes(11));

    $expectedOrder = $onA->sortByDesc('id')->pluck('id')->values()->all();

    $seenIds = [];
    $cursor = null;
    $hasMore = true;
    $pageCount = 0;

    while ($hasMore) {
        $pageCount++;
        expect($pageCount)->toBeLessThanOrEqual(10); // safety valve against an infinite-loop regression

        $query = "entity_type=request-management&entity_id={$opportunity->id}&quote_scope={$quoteA->id}&limit=2";
        $response = $this->getJson('/api/notes?'.$query.($cursor !== null ? "&cursor={$cursor}" : ''))->assertOk();

        $pageIds = collect($response->json('data'))->pluck('id')->all();
        expect(array_intersect($pageIds, $seenIds))->toBe([]);
        $seenIds = array_merge($seenIds, $pageIds);

        $hasMore = $response->json('meta.has_more');
        $cursor = $response->json('meta.next_cursor');
        expect($hasMore)->toBe($cursor !== null);
    }

    expect($seenIds)->toBe($expectedOrder);
});

it('replies of a filtered-in root are always present, regardless of their OWN quote_id (AC-016)', function () {
    $actor = noteActor(['request-management.view']);
    $opportunity = noteManagedOpportunity($actor);
    $quoteA = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    $quoteB = Quote::factory()->create(['opportunity_id' => $opportunity->id]);
    Sanctum::actingAs($actor);

    $root = rootNote($opportunity, $quoteA->id, now());

    $sameScopeReply = Note::factory()->create([
        'notable_type' => 'opportunity', 'notable_id' => $opportunity->id,
        'parent_id' => $root->id, 'created_at' => now()->addMinute(),
    ]);
    $sameScopeReply->forceFill(['quote_id' => $quoteA->id])->save();

    // A data state the write path never produces on its own (D-4 always
    // inherits the root's quote_id) — forced here to prove the LIST query
    // itself never re-applies the scope predicate to replies.
    $divergentReply = Note::factory()->create([
        'notable_type' => 'opportunity', 'notable_id' => $opportunity->id,
        'parent_id' => $root->id, 'created_at' => now()->addMinutes(2),
    ]);
    $divergentReply->forceFill(['quote_id' => $quoteB->id])->save();

    $response = $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope={$quoteA->id}")->assertOk();

    $rootPayload = collect($response->json('data'))->firstWhere('id', $root->id);
    expect(collect($rootPayload['replies'])->pluck('id')->all())->toBe([$sameScopeReply->id, $divergentReply->id]);
});

it('an actor who fails authorizeRead gets 403 even with a valid quote_scope (AC-017)', function () {
    $actor = noteActor([]); // no request-management.view
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=request-management&entity_id={$opportunity->id}&quote_scope=general")
        ->assertForbidden();
});
