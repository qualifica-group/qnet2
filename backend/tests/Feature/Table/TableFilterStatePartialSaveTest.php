<?php

use App\Models\Source;
use App\Models\UserTableFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * POST /api/tables/{domain}/filters persists each key independently: the
 * advanced-filters panel's Apply sends ONLY `advancedFilters`, a column filter
 * change sends ONLY `filterModel`. Omitting a key keeps what is stored for it,
 * so neither save may wipe (or be rejected for lacking) the other.
 */
const LEAD_STATUS_FILTER = ['lead_status' => ['filterType' => 'set', 'values' => ['associated']]];

it('persists advancedFilters sent without filterModel and keeps the saved column filters', function () {
    $user = leadConversionActor(['viewAny'], []);
    Sanctum::actingAs($user);
    $source = Source::factory()->create();

    $this->postJson('/api/tables/leads/filters', ['filterModel' => LEAD_STATUS_FILTER])->assertOk();

    $this->postJson('/api/tables/leads/filters', ['advancedFilters' => ['source' => [$source->id]]])
        ->assertOk()
        ->assertJsonPath('data.appliedAdvancedFilters.source', [$source->id]);

    $config = $this->getJson('/api/tables/leads/columns')->json('data');
    expect($config['appliedAdvancedFilters'])->toBe(['source' => [$source->id]])
        ->and($config['filterState'])->toBe(LEAD_STATUS_FILTER);
});

it('keeps the saved advancedFilters when only filterModel is sent', function () {
    $user = leadConversionActor(['viewAny'], []);
    Sanctum::actingAs($user);
    $source = Source::factory()->create();

    $this->postJson('/api/tables/leads/filters', ['advancedFilters' => ['source' => [$source->id]]])->assertOk();
    $this->postJson('/api/tables/leads/filters', ['filterModel' => LEAD_STATUS_FILTER])->assertOk();

    $config = $this->getJson('/api/tables/leads/columns')->json('data');
    expect($config['appliedAdvancedFilters'])->toBe(['source' => [$source->id]])
        ->and($config['filterState'])->toBe(LEAD_STATUS_FILTER);
});

it('clears the stored row once advancedFilters are emptied and no column filter is saved', function () {
    $user = leadConversionActor(['viewAny'], []);
    Sanctum::actingAs($user);
    $source = Source::factory()->create();

    $this->postJson('/api/tables/leads/filters', ['advancedFilters' => ['source' => [$source->id]]])->assertOk();
    $this->postJson('/api/tables/leads/filters', ['advancedFilters' => []])->assertOk();

    expect(UserTableFilter::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('rejects a save carrying neither filterModel nor advancedFilters with 422', function () {
    Sanctum::actingAs(leadConversionActor(['viewAny'], []));

    $this->postJson('/api/tables/leads/filters', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filterModel');
});
