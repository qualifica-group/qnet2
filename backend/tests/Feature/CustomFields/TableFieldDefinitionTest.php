<?php

use App\Jobs\PromoteCustomFieldIndexJob;
use App\Models\CustomFieldDefinition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// spec 0180 — B2: config validation of a `table` custom field definition (AC-002/003/004).
uses(RefreshDatabase::class);

function tableDefinitionAdmin(): User
{
    foreach (['create', 'update'] as $ability) {
        Permission::findOrCreate("custom-fields.{$ability}");
    }

    return tap(User::factory()->create())->givePermissionTo(['custom-fields.create', 'custom-fields.update']);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tableDefinitionPayload(array $overrides = []): array
{
    return [...['entity_type' => 'companies', 'key' => 'inspections', 'type' => 'table', 'label' => 'Inspections', 'config' => inspectionTableConfig()], ...$overrides];
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

it('AC-002: creates a table field and persists the config identically', function () {
    Sanctum::actingAs(tableDefinitionAdmin());

    $this->postJson('/api/custom-fields', tableDefinitionPayload())
        ->assertCreated()
        ->assertJsonPath('data.type', 'table');

    expect(CustomFieldDefinition::where('key', 'inspections')->first()->config)->toBe(inspectionTableConfig());
});

it('AC-003: rejects an invalid table config', function (array $config, string $errorKey) {
    Sanctum::actingAs(tableDefinitionAdmin());

    $this->postJson('/api/custom-fields', tableDefinitionPayload(['config' => $config]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($errorKey);

    expect(CustomFieldDefinition::count())->toBe(0);
})->with('invalid table configs');

it('AC-003: rejects a table field without config', function () {
    Sanctum::actingAs(tableDefinitionAdmin());

    $this->postJson('/api/custom-fields', tableDefinitionPayload(['config' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('config');
});

it('AC-003: update validates a submitted config, and the persisted one on a type change to table', function () {
    Sanctum::actingAs(tableDefinitionAdmin());
    $table = tableCustomField();
    $text = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['key' => 'plain']);

    $this->patchJson("/api/custom-fields/{$table->id}", ['config' => inspectionTableConfig(['max_rows' => 201])])
        ->assertStatus(422)->assertJsonValidationErrors('config.max_rows');
    $this->patchJson("/api/custom-fields/{$table->id}", ['label' => 'Renamed'])->assertOk();
    $this->patchJson("/api/custom-fields/{$text->id}", ['type' => 'table'])
        ->assertStatus(422)->assertJsonValidationErrors('config');
    $this->patchJson("/api/custom-fields/{$text->id}", ['type' => 'table', 'config' => inspectionTableConfig()])->assertOk();
});

it('AC-004: rejects is_indexed on a table field and dispatches no promotion job', function () {
    Queue::fake();
    Sanctum::actingAs(tableDefinitionAdmin());

    $this->postJson('/api/custom-fields', tableDefinitionPayload(['is_indexed' => true]))
        ->assertStatus(422)->assertJsonValidationErrors('is_indexed');

    $table = tableCustomField();
    $this->patchJson("/api/custom-fields/{$table->id}", ['is_indexed' => true])
        ->assertStatus(422)->assertJsonValidationErrors('is_indexed');

    expect(CustomFieldDefinition::count())->toBe(1);
    Queue::assertNotPushed(PromoteCustomFieldIndexJob::class);
});
