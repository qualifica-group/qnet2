<?php

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteStatus;
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
