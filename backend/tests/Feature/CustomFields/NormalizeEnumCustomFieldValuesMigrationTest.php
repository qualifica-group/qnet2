<?php

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The legacy product import stored the "Cartella" folder as its label
 * ("Consulenza") instead of the option value ("consulenza"): the product
 * edit form then refused every save. The data repair maps such values back.
 */
function runEnumValuesNormalization(): void
{
    $migration = include database_path('migrations/2026_12_16_100000_normalize_enum_custom_field_values_to_option_values.php');
    $migration->up();
}

function folderDefinition(array $config = []): CustomFieldDefinition
{
    $definition = CustomFieldDefinition::factory()->forEntity('products')->ofType('enum')
        ->create(['key' => 'folder', 'config' => $config === [] ? null : $config]);
    CustomFieldOption::query()->create(['definition_id' => $definition->id, 'value' => 'ente', 'label' => 'Ente']);
    CustomFieldOption::query()->create(['definition_id' => $definition->id, 'value' => 'consulenza', 'label' => 'Consulenza']);

    return $definition;
}

it('rewrites a stored option label to its option value and leaves valid or unknown values alone', function () {
    folderDefinition();
    $label = CustomFieldValue::factory()->forEntity('products', 1)->create(['values' => ['folder' => 'Consulenza', 'expiration_months' => 0]]);
    $padded = CustomFieldValue::factory()->forEntity('products', 2)->create(['values' => ['folder' => ' ENTE ']]);
    $valid = CustomFieldValue::factory()->forEntity('products', 3)->create(['values' => ['folder' => 'ente']]);
    $unknown = CustomFieldValue::factory()->forEntity('products', 4)->create(['values' => ['folder' => 'Archivio']]);
    $without = CustomFieldValue::factory()->forEntity('products', 5)->create(['values' => ['expiration_months' => 6]]);
    $otherEntity = CustomFieldValue::factory()->forEntity('registries', 1)->create(['values' => ['folder' => 'Consulenza']]);

    runEnumValuesNormalization();

    expect($label->fresh()->values)->toBe(['folder' => 'consulenza', 'expiration_months' => 0])
        ->and($padded->fresh()->values)->toBe(['folder' => 'ente'])
        ->and($valid->fresh()->values)->toBe(['folder' => 'ente'])
        ->and($unknown->fresh()->values)->toBe(['folder' => 'Archivio'])
        ->and($without->fresh()->values)->toBe(['expiration_months' => 6])
        ->and($otherEntity->fresh()->values)->toBe(['folder' => 'Consulenza']);
});

it('rewrites each item of a multiselect enum', function () {
    folderDefinition(['display' => 'multiselect']);
    $value = CustomFieldValue::factory()->forEntity('products', 1)->create(['values' => ['folder' => ['Ente', 'consulenza']]]);

    runEnumValuesNormalization();

    expect($value->fresh()->values)->toBe(['folder' => ['ente', 'consulenza']]);
});
