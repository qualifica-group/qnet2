<?php

use App\Models\Registry;
use Database\Seeders\Support\DemoAttributeValueFaker;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The demo attribute faker must produce what AttributeValueValidator accepts
 * per type: a plain sentence for a relation/multiselect/table attribute
 * aborted DemoQuoteSeeder and, with it, every downstream demo seeder.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function demoAttribute(string $type, array $overrides = []): array
{
    return ['code' => 'demo', 'type' => $type, 'config' => null, 'relation_target' => null, 'options' => [], ...$overrides];
}

it('fakes a relation as a real id of its target entity, an array for cardinality many', function (): void {
    $registry = Registry::factory()->create();
    $faker = app(DemoAttributeValueFaker::class);
    $generator = FakerFactory::create();

    expect($faker->fake($generator, demoAttribute('relation', ['relation_target' => ['entity_type' => 'registries', 'cardinality' => 'one']])))->toBe($registry->id)
        ->and($faker->fake($generator, demoAttribute('relation', ['relation_target' => ['entity_type' => 'registries', 'cardinality' => 'many']])))->toBe([$registry->id])
        ->and($faker->fake($generator, demoAttribute('relation', ['relation_target' => ['entity_type' => 'unknown-entity']])))->toBeNull();
});

it('fakes a multiselect enum as an array and a single enum as its first option', function (): void {
    $faker = app(DemoAttributeValueFaker::class);
    $generator = FakerFactory::create();
    $options = [['value' => 'a'], ['value' => 'b']];

    expect($faker->fake($generator, demoAttribute('enum', ['options' => $options, 'config' => ['display' => 'multiselect']])))->toBe(['a'])
        ->and($faker->fake($generator, demoAttribute('enum', ['options' => $options])))->toBe('a');
});

it('fakes table, time, email, url and color in the shape the validator expects', function (): void {
    $faker = app(DemoAttributeValueFaker::class);
    $generator = FakerFactory::create();

    expect($faker->fake($generator, demoAttribute('table')))->toBe([])
        ->and($faker->fake($generator, demoAttribute('time')))->toMatch('/^\d{2}:\d{2}$/')
        ->and(filter_var($faker->fake($generator, demoAttribute('email')), FILTER_VALIDATE_EMAIL))->not->toBeFalse()
        ->and(filter_var($faker->fake($generator, demoAttribute('url')), FILTER_VALIDATE_URL))->not->toBeFalse()
        ->and($faker->fake($generator, demoAttribute('color')))->toMatch('/^#[0-9a-fA-F]{6}$/');
});
