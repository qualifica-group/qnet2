<?php

use App\Models\Attribute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0180 — B2: config validation of a `table` attribute definition (AC-002/003).
uses(RefreshDatabase::class);

function tableAttributeAdmin(): User
{
    foreach (['create', 'update'] as $ability) {
        Permission::findOrCreate("attributes.{$ability}");
    }

    return tap(User::factory()->create())->givePermissionTo(['attributes.create', 'attributes.update']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tableAttributePayload(array $overrides = []): array
{
    return [...['code' => 'inspections', 'name' => 'Inspections', 'type' => 'table', 'config' => inspectionTableConfig()], ...$overrides];
}

dataset('invalid table configs', function () {
    $cols = inspectionTableConfig()['columns'];
    $column = fn (array $overrides = []) => [...['key' => 'extra', 'label' => 'Extra', 'type' => 'text'], ...$overrides];

    return [
        'no columns' => [inspectionTableConfig(['columns' => []]), 'config.columns'],
        '21 columns' => [inspectionTableConfig(['columns' => array_map(fn (int $i) => $column(['key' => "c{$i}"]), range(1, 21)), 'selectable' => null, 'summary' => null]), 'config.columns'],
        'duplicate key' => [inspectionTableConfig(['columns' => [...$cols, $column(['key' => 'inspector'])]]), 'config.columns.5.key'],
        'reserved key id' => [inspectionTableConfig(['columns' => [...$cols, $column(['key' => 'id'])]]), 'config.columns.5.key'],
        'invalid key format' => [inspectionTableConfig(['columns' => [...$cols, $column(['key' => 'Bad-Key'])]]), 'config.columns.5.key'],
        'relation type' => [inspectionTableConfig(['columns' => [...$cols, $column(['type' => 'relation'])]]), 'config.columns.5.type'],
        'enum without options' => [inspectionTableConfig(['columns' => [...$cols, $column(['type' => 'enum'])]]), 'config.columns.5.options'],
        'duplicate option values' => [inspectionTableConfig(['columns' => [...$cols, $column(['type' => 'enum', 'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'B']]])]]), 'config.columns.5.options'],
        'enum multiselect display' => [inspectionTableConfig(['columns' => [...$cols, $column(['type' => 'enum', 'config' => ['display' => 'multiselect'], 'options' => [['value' => 'a', 'label' => 'A']]])]]), 'config.columns.5.config.display'],
        'selectable equals column' => [inspectionTableConfig(['selectable' => ['key' => 'inspector', 'label' => 'Sel']]), 'config.selectable.key'],
        'selectable reserved' => [inspectionTableConfig(['selectable' => ['key' => 'id', 'label' => 'Sel']]), 'config.selectable.key'],
        'summary column missing' => [inspectionTableConfig(['summary' => ['column' => 'nope', 'strategy' => 'max']]), 'config.summary.column'],
        'summary on boolean' => [inspectionTableConfig(['summary' => ['column' => 'alert_sent', 'strategy' => 'max']]), 'config.summary.column'],
        'selected without selectable' => [inspectionTableConfig(['selectable' => null]), 'config.summary.strategy'],
        'unknown strategy' => [inspectionTableConfig(['summary' => ['column' => 'inspection_date', 'strategy' => 'avg']]), 'config.summary.strategy'],
        'min_rows above max_rows' => [inspectionTableConfig(['min_rows' => 5, 'max_rows' => 3]), 'config.min_rows'],
        'max_rows above 200' => [inspectionTableConfig(['max_rows' => 201]), 'config.max_rows'],
        'max_rows zero' => [inspectionTableConfig(['max_rows' => 0]), 'config.max_rows'],
    ];
});

it('AC-002: creates a table attribute and persists the config identically', function () {
    Sanctum::actingAs(tableAttributeAdmin());

    $this->postJson('/api/attributes', tableAttributePayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'table');

    expect(Attribute::where('code', 'inspections')->first()->config)->toBe(inspectionTableConfig());
});

it('AC-003: rejects an invalid table config', function (array $config, string $errorKey) {
    Sanctum::actingAs(tableAttributeAdmin());

    $this->postJson('/api/attributes', tableAttributePayload(['config' => $config]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($errorKey);

    expect(Attribute::count())->toBe(0);
})->with('invalid table configs');

it('AC-003: rejects a table attribute without config', function () {
    Sanctum::actingAs(tableAttributeAdmin());

    $this->postJson('/api/attributes', tableAttributePayload(['config' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('config');
});

it('AC-003: update validates a submitted config, and the persisted one on a type change to table', function () {
    Sanctum::actingAs(tableAttributeAdmin());
    $this->postJson('/api/attributes', tableAttributePayload())->assertCreated();
    $table = Attribute::where('code', 'inspections')->firstOrFail();
    $text = Attribute::factory()->create(['code' => 'plain', 'type' => 'text']);

    $this->patchJson("/api/attributes/{$table->id}", ['config' => inspectionTableConfig(['max_rows' => 201])])
        ->assertStatus(422)->assertJsonValidationErrors('config.max_rows');
    $this->patchJson("/api/attributes/{$table->id}", ['name' => 'Renamed'])->assertOk();
    $this->patchJson("/api/attributes/{$text->id}", ['type' => 'table'])
        ->assertStatus(422)->assertJsonValidationErrors('config');
});
