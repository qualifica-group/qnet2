<?php

use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sectorCodeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sectorCodeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sectors.{$ability}");
        }

        return $user;
    }
}

it('AC-001: stores the code verbatim and exposes it', function () {
    Sanctum::actingAs(sectorCodeUserWith(['create']));

    $this->postJson('/api/sectors', ['code' => '07a', 'name' => 'Prodotti in carta'])
        ->assertCreated()
        ->assertJsonPath('data.code', '07a');

    expect(Sector::firstWhere('name', 'Prodotti in carta')->code)->toBe('07a');
});

it('AC-001: a sector without a code stays valid', function () {
    Sanctum::actingAs(sectorCodeUserWith(['create']));

    $this->postJson('/api/sectors', ['name' => 'No code'])
        ->assertCreated()
        ->assertJsonPath('data.code', null);
});

it('AC-001: a code already used is rejected, the own code is accepted back', function () {
    $taken = Sector::factory()->create(['code' => '01']);
    $other = Sector::factory()->create(['code' => '02']);
    Sanctum::actingAs(sectorCodeUserWith(['create', 'update']));

    $this->postJson('/api/sectors', ['code' => '01', 'name' => 'Duplicate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->patchJson("/api/sectors/{$other->id}", ['code' => '01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->patchJson("/api/sectors/{$taken->id}", ['code' => '01', 'name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.code', '01');
});

it('AC-001: PATCH code=null clears it, omitting it leaves it untouched', function () {
    $sector = Sector::factory()->create(['code' => '33']);
    Sanctum::actingAs(sectorCodeUserWith(['update']));

    $this->patchJson("/api/sectors/{$sector->id}", ['name' => 'IT'])->assertOk();
    expect($sector->fresh()->code)->toBe('33');

    $this->patchJson("/api/sectors/{$sector->id}", ['code' => null])->assertOk();
    expect($sector->fresh()->code)->toBeNull();
});

it('AC-002: for-select searches by code and labels the sector "code — name"', function () {
    $target = Sector::factory()->create(['code' => '07a', 'name' => 'Prodotti in carta']);
    Sector::factory()->create(['code' => '08', 'name' => 'Case editrici']);
    Sanctum::actingAs(sectorCodeUserWith([]));

    $items = $this->getJson('/api/sectors/for-select?search=07a')->assertOk()->json('items');

    expect($items)->toBe([['id' => $target->id, 'label' => '07a — Prodotti in carta']]);
});

it('AC-002: a sector without a code is labelled by its name alone, also when hydrated by id', function () {
    $coded = Sector::factory()->create(['code' => '33', 'name' => 'Tecnologia']);
    $plain = Sector::factory()->create(['code' => null, 'name' => 'Varie']);
    Sanctum::actingAs(sectorCodeUserWith([]));

    $items = $this->getJson("/api/sectors/for-select?search=zzz&ids[]={$coded->id}&ids[]={$plain->id}")
        ->assertOk()
        ->json('items');

    expect(collect($items)->pluck('label', 'id')->all())
        ->toBe([$coded->id => '33 — Tecnologia', $plain->id => 'Varie']);
});

it('AC-003: the grid exposes the code column and quick-searches it', function () {
    $target = Sector::factory()->create(['code' => '17b']);
    Sector::factory()->create(['code' => '18']);
    Sanctum::actingAs(sectorCodeUserWith(['viewAny']));

    $columns = collect($this->getJson('/api/tables/sectors/columns')->assertOk()->json('data.columns'))->keyBy('id');
    expect($columns['code']['sortable'])->toBeTrue()
        ->and($columns['code']['filterType'])->toBe('text');

    $rows = $this->postJson('/api/tables/sectors/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => '17b',
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('id')->all())->toBe([$target->id])
        ->and($rows[0]['code'])->toBe('17b');
});

it('AC-002: the tree nodes carry the code, so the parent picker can label them "code — name"', function () {
    $root = Sector::factory()->create(['code' => '07', 'name' => 'Carta']);
    Sector::factory()->childOf($root)->create(['code' => null, 'name' => 'Imballaggi']);
    Sanctum::actingAs(sectorCodeUserWith(['viewAny']));

    $node = $this->getJson('/api/sectors/tree')->assertOk()->json('data.0');

    expect($node['code'])->toBe('07')
        ->and($node['children'][0]['code'])->toBeNull();
});
