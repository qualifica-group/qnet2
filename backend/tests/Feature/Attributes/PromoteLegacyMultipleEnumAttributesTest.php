<?php

use App\Models\Attribute;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The q-crm import expressed a multi-choice enum as `{"display": "select",
 * "multiple": true}` ("Categorie ME.PA."), a key nothing here reads: the field
 * behaved as a single pick. The migration promotes it to the `multiselect`
 * display every layer recognises and wraps its stored scalars into lists.
 */
function legacyMultipleMigration(): object
{
    return require database_path('migrations/2026_09_25_140000_promote_legacy_multiple_enum_attributes.php');
}

function quoteAttributeValues(Quote $quote): array
{
    return json_decode((string) DB::table('quotes')->where('id', $quote->id)->value('attribute_values'), true);
}

it('promotes a legacy multiple enum to multiselect and wraps its stored scalars', function (): void {
    Attribute::factory()->create(['code' => 'categorie_mepa', 'type' => 'enum', 'config' => ['display' => 'select', 'multiple' => true]]);
    $scalar = Quote::factory()->create(['attribute_values' => ['categorie_mepa' => '19', 'other' => 'x']]);
    $list = Quote::factory()->create(['attribute_values' => ['categorie_mepa' => ['19', '20']]]);
    $workOrder = WorkOrder::factory()->create(['attribute_values' => ['categorie_mepa' => '20']]);

    legacyMultipleMigration()->up();

    expect(Attribute::query()->where('code', 'categorie_mepa')->value('config'))
        ->toBe(['display' => 'multiselect', 'multiple' => true])
        ->and(quoteAttributeValues($scalar))->toBe(['categorie_mepa' => ['19'], 'other' => 'x'])
        ->and(quoteAttributeValues($list))->toBe(['categorie_mepa' => ['19', '20']])
        ->and($workOrder->fresh()->attribute_values)->toBe(['categorie_mepa' => ['20']]);
});

it('leaves every other enum attribute and its values untouched', function (): void {
    Attribute::factory()->create(['code' => 'stato', 'type' => 'enum', 'config' => ['display' => 'select']]);
    $quote = Quote::factory()->create(['attribute_values' => ['stato' => 'open']]);

    legacyMultipleMigration()->up();

    expect(Attribute::query()->where('code', 'stato')->value('config'))->toBe(['display' => 'select'])
        ->and(quoteAttributeValues($quote))->toBe(['stato' => 'open']);
});

it('down() restores the single pick, keeping the first code of each list', function (): void {
    Attribute::factory()->create(['code' => 'categorie_mepa', 'type' => 'enum', 'config' => ['display' => 'select', 'multiple' => true]]);
    $quote = Quote::factory()->create(['attribute_values' => ['categorie_mepa' => '19']]);
    $migration = legacyMultipleMigration();

    $migration->up();
    $migration->down();

    expect(Attribute::query()->where('code', 'categorie_mepa')->value('config'))
        ->toBe(['display' => 'select', 'multiple' => true])
        ->and(quoteAttributeValues($quote))->toBe(['categorie_mepa' => '19']);
});
