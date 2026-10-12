<?php

use App\Models\RewardType;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The default hidden `old_id` column (InjectsDefaultIdColumn): injected right
 * after `id` on every table whose model table carries the legacy id, absent
 * elsewhere, and backed by the row payload (TableService).
 */
function legacyIdActorFor(string $domain): User
{
    Permission::findOrCreate("{$domain}.viewAny");

    $user = User::factory()->create();
    $user->givePermissionTo("{$domain}.viewAny");

    return $user;
}

it('injects a hidden sortable old_id column right after id when the table has a legacy id', function () {
    Sanctum::actingAs(legacyIdActorFor('tags'));

    $columns = collect($this->getJson('/api/tables/tags/columns')->assertOk()->json('data.columns'));

    expect($columns->pluck('id')->take(2)->all())->toBe(['id', 'old_id']);

    expect($columns->firstWhere('id', 'old_id'))
        ->label->toBe('table.columns.old_id')
        ->type->toBe('number')
        ->visible->toBeFalse()
        ->sortable->toBeTrue()
        ->filterable->toBeFalse()
        ->order->toBe(2);
});

it('places old_id after a table-declared id column', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $ids = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');
    $idPosition = $ids->search('id');

    expect($ids->filter(fn (string $id): bool => $id === 'old_id'))->toHaveCount(1)
        ->and($ids->get($idPosition + 1))->toBe('old_id');
});

it('does not inject old_id on a table without a legacy id', function () {
    Sanctum::actingAs(legacyIdActorFor('reward-types'));

    $ids = collect($this->getJson('/api/tables/reward-types/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($ids)->toContain('id')->not->toContain('old_id');
});

it('returns old_id in the rows and sorts by it', function () {
    Sanctum::actingAs(legacyIdActorFor('tags'));

    Tag::factory()->create(['old_id' => 20]);
    Tag::factory()->create(['old_id' => 30]);
    Tag::factory()->create(['old_id' => 10]);

    $rows = $this->postJson('/api/tables/tags/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'old_id', 'sort' => 'desc']],
    ])->assertOk()->json('items');

    expect(array_column($rows, 'old_id'))->toBe([30, 20, 10]);
});

it('leaves the row payload without old_id on a table without a legacy id', function () {
    Sanctum::actingAs(legacyIdActorFor('reward-types'));

    RewardType::factory()->create();

    $rows = $this->postJson('/api/tables/reward-types/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items');

    expect($rows)->not->toBeEmpty()
        ->and($rows[0])->not->toHaveKey('old_id');
});
