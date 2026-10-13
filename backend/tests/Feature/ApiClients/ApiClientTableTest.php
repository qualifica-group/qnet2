<?php

use App\Models\ApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('denies the table without api-clients.view and requires authentication', function () {
    $this->getJson('/api/tables/api-clients/columns')->assertUnauthorized();

    Sanctum::actingAs(apiClientAdmin(['create', 'update', 'delete']));
    $this->getJson('/api/tables/api-clients/columns')->assertForbidden();
    $this->postJson('/api/tables/api-clients/rows', ['startRow' => 0, 'endRow' => 25])->assertForbidden();
});

it('serves the declared columns, sort and the row action catalogue', function () {
    Sanctum::actingAs(apiClientAdmin(['view', 'update', 'delete']));

    $data = $this->getJson('/api/tables/api-clients/columns')
        ->assertOk()
        ->assertJsonPath('data.resource', 'api-clients')
        ->json('data');

    expect(collect($data['columns'])->pluck('id')->all())->toBe([
        'id', 'name', 'is_active', 'expires_at', 'key_last_four', 'last_used_at', 'created_by', 'created_at',
    ])
        ->and(collect($data['actions'])->pluck('key')->all())->toBe(['view', 'edit', 'rotate-key', 'delete']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns->has('scopes'))->toBeFalse()
        ->and($columns['last_used_at']['sortable'])->toBeTrue()
        ->and($columns['last_used_at']['filterable'])->toBeFalse();
});

it('only offers the row actions the actor may perform', function (array $abilities, array $expected) {
    createApiClientWithKey();
    Sanctum::actingAs(apiClientAdmin($abilities));

    $row = $this->postJson('/api/tables/api-clients/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items.0');

    expect($row['actions'])->toBe($expected);
})->with([
    'view only' => [['view'], ['view']],
    'view + update' => [['view', 'update'], ['view', 'edit', 'rotate-key']],
    'view + delete' => [['view', 'delete'], ['view', 'delete']],
    'everything' => [['view', 'update', 'delete'], ['view', 'edit', 'rotate-key', 'delete']],
]);

it('maps the row without any key material and sorts by last_used_at from the allow-list', function () {
    [$recent] = createApiClientWithKey(['name' => 'Recent']);
    [$stale] = createApiClientWithKey(['name' => 'Stale']);
    $recent->tokens()->update(['last_used_at' => now()]);
    $stale->tokens()->update(['last_used_at' => now()->subDay()]);

    Sanctum::actingAs(apiClientAdmin(['view']));

    $rows = $this->postJson('/api/tables/api-clients/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'last_used_at', 'sort' => 'desc']],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('name')->all())->toBe(['Recent', 'Stale'])
        ->and(array_keys($rows[0]))->toBe([
            'id', 'name', 'is_active', 'expires_at', 'key_last_four', 'last_used_at', 'created_by', 'created_at', 'actions', 'editable',
        ])
        ->and(ApiClient::query()->count())->toBe(2);
});
