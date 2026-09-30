<?php

use App\Models\PipelineStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('pipelineStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function pipelineStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("pipeline-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("pipeline-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/pipeline-statuses/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = pipelineStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/pipeline-statuses/columns')->assertForbidden();

    $actor = pipelineStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/pipeline-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('pipeline-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    // requirement changed (spec 0039 pivot): `group` is a new real column
    // between `sort_order` and `created_at`.
    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'color', 'sort_order', 'group', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['name']['sortable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// AC-030 — rows shape
// ---------------------------------------------------------------------------

it('rows expose name/color/sort_order/created_at + per-row actions', function () {
    $actor = pipelineStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    PipelineStatus::factory()->create(['name' => 'Attivo', 'color' => '#ff0000', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/pipeline-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Attivo');

    expect($row)->not->toBeNull()
        ->and($row['color'])->toBe('#ff0000')
        ->and($row['sort_order'])->toBe(1)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'delete']);
});
