<?php

use App\Migrations\MigrationOrder;
use App\Migrations\MigrationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('every dependency of a registered source is registered and imported in an earlier phase', function () {
    foreach (app(MigrationRegistry::class)->all() as $source) {
        $phase = MigrationOrder::phaseOf($source->key());
        expect($phase)->not->toBeNull();

        foreach ($source->dependencies() as $dependency) {
            expect(config('migrations.definitions'))->toHaveKey($dependency->source)
                ->and(MigrationOrder::phaseOf($dependency->source))->toBeLessThan(
                    $phase,
                    "{$source->key()} depends on {$dependency->source}, which is not imported before it",
                );
        }
    }
});

it('no source declares the same dependency twice', function () {
    foreach (app(MigrationRegistry::class)->all() as $source) {
        $keys = array_map(fn ($dependency) => $dependency->source, $source->dependencies());

        expect($keys)->toBe(array_values(array_unique($keys)));
    }
});

it('phaseOf gives the 1-based phase, or null for a source outside the order', function () {
    expect(MigrationOrder::phaseOf('roles'))->toBe(1)
        ->and(MigrationOrder::phaseOf('users'))->toBe(2)
        ->and(MigrationOrder::phaseOf('registries'))->toBe(7)
        ->and(MigrationOrder::phaseOf('work-orders'))->toBe(11)
        ->and(MigrationOrder::phaseOf('tasks'))->toBe(12)
        // REQUIREMENT CHANGED (spec 0294): `purchase-requests` is the new last phase, after `tasks`.
        ->and(MigrationOrder::phaseOf('purchase-requests'))->toBe(count(MigrationOrder::phases()))
        ->and(MigrationOrder::phaseOf('unknown-source'))->toBeNull();
});
