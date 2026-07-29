<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Backend-driven table for the `quotes` domain (spec 0065, MT-05): the
 * declared columns (AC-069c), and per-row actions gated by QuotePolicy.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

it('AC-069c: GET /api/tables/quotes/columns declares code as sortable, filterable and searchable', function () {
    $actor = quoteTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/quotes/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('quotes');

    $columns = collect($data['columns']);
    $code = $columns->firstWhere('id', 'code');

    expect($code)->not->toBeNull()
        ->and($code['sortable'])->toBeTrue()
        ->and($code['filterable'])->toBeTrue();

    // `searchable` is exposed at the top-level `data.searchable` allow-list
    // (the real DB columns the quick-search spans), not as a per-column key.
    expect($data['searchable'])->toContain('code');

    $ids = $columns->pluck('id')->all();
    expect($ids)->toBe([
        'id', 'code', 'title', 'opportunity', 'quote_status', 'commercial',
        'reporter', 'supervisor', 'revenue_net', 'cost_net', 'margin_net', 'created_at',
    ]);
});

it('rows: view/edit/delete/activity actions gated by QuotePolicy', function () {
    $fullActor = quoteTableUserWith(['viewAny', 'view', 'update', 'delete', 'viewActivity']);
    $opportunity = Opportunity::factory()->create();
    Quote::factory()->create(['opportunity_id' => $opportunity->id, 'title' => 'Riga completa']);
    Sanctum::actingAs($fullActor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('title', 'Riga completa');

    expect($row)->not->toBeNull()
        ->and($row['actions'])->toContain('view', 'edit', 'delete', 'activity');

    $readOnlyActor = quoteTableUserWith(['viewAny', 'view']);
    Sanctum::actingAs($readOnlyActor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('title', 'Riga completa');

    expect($row['actions'])->toContain('view')
        ->and($row['actions'])->not->toContain('edit', 'delete', 'activity');
});

it('rows: the quote_status column carries id/name/color for the badge', function () {
    $actor = quoteTableUserWith(['viewAny', 'view']);
    $status = QuoteStatus::factory()->create(['name' => 'In revisione', 'color' => 'amber']);
    Quote::factory()->create(['quote_status_id' => $status->id, 'title' => 'Badge test']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('title', 'Badge test');

    expect($row['quote_status'])->toBe(['id' => $status->id, 'name' => 'In revisione', 'color' => 'amber']);
});

/**
 * Bug repro (user report: "la tabella offerte esce ERR su ogni colonna"):
 * pins down the EXACT shape of every mapRow field the frontend renderers
 * consume, for a row with every nullable relation POPULATED. Guards against a
 * silent shape drift (e.g. a missing `avatar_url` on `supervisor`, the one key
 * `UserCell` shares with every other person column in the app) going
 * unnoticed because the cell defensively falls back on absence instead of
 * throwing.
 */
it('rows: a fully-populated quote projects the exact frontend-consumed shape (all relations set)', function () {
    $actor = quoteTableUserWith(['viewAny', 'view']);
    $opportunity = Opportunity::factory()->create(['name' => 'Rinnovo contratto']);
    $status = QuoteStatus::factory()->create(['name' => 'In negoziazione', 'color' => 'green']);
    $commercial = Referent::factory()->create(['name' => 'Mario Rossi']);
    $reporter = Referent::factory()->create(['name' => 'Luca Bianchi']);
    $supervisor = User::factory()->create(['name' => 'Giulia Verdi']);
    $quote = Quote::factory()->create([
        'title' => 'Offerta completa',
        'opportunity_id' => $opportunity->id,
        'quote_status_id' => $status->id,
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row)->not->toBeNull()
        ->and($row['opportunity'])->toBe(['id' => $opportunity->id, 'name' => 'Rinnovo contratto'])
        ->and($row['quote_status'])->toBe(['id' => $status->id, 'name' => 'In negoziazione', 'color' => 'green'])
        ->and($row['commercial'])->toBe(['id' => $commercial->id, 'name' => 'Mario Rossi'])
        ->and($row['reporter'])->toBe(['id' => $reporter->id, 'name' => 'Luca Bianchi'])
        // `supervisor` is rendered by the SAME shared `UserCell` component as
        // Opportunities' supervisor/managers — which always carries
        // `avatar_url` (via `userSummary()`). Quotes currently project it via
        // the generic `summarize()` helper, which OMITS the key entirely.
        ->and($row['supervisor'])->toHaveKeys(['id', 'name', 'avatar_url'])
        ->and($row['supervisor']['id'])->toBe($supervisor->id)
        ->and($row['supervisor']['name'])->toBe('Giulia Verdi')
        ->and($row['revenue_net'])->toBeString()
        ->and($row['cost_net'])->toBeString()
        ->and($row['margin_net'])->toBeString()
        ->and($row['created_at'])->toBeString();
});

/**
 * Bug repro, mirror case: every nullable relation ABSENT (the exact shape a
 * freshly-created quote or a demo-seeded row without commercial/reporter/
 * supervisor carries — confirmed present in the real dataset).
 */
it('rows: a quote with every nullable relation absent projects null summaries, not an error', function () {
    $actor = quoteTableUserWith(['viewAny', 'view']);
    $quote = Quote::factory()->create([
        'title' => 'Offerta senza referenti',
        'commercial_id' => null,
        'reporter_id' => null,
        'supervisor_id' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $quote->id);

    expect($row)->not->toBeNull()
        ->and($row['commercial'])->toBeNull()
        ->and($row['reporter'])->toBeNull()
        ->and($row['supervisor'])->toBeNull();
});
