<?php

declare(strict_types=1);

use App\FieldChangeRequests\ProtectedField;
use App\FieldChangeRequests\ProtectedFieldRegistry;

// AC-001 (prerequisite): the shipped config resolves into ProtectedField
// value objects with the frozen shape.
it('resolves the shipped config into ProtectedField value objects', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    $fields = $registry->forResource('request-management');

    expect($fields)->toHaveKey('source_id')
        ->and($fields['source_id'])->toBeInstanceOf(ProtectedField::class);

    $sourceId = $fields['source_id'];

    expect($sourceId->resource)->toBe('request-management')
        ->and($sourceId->field)->toBe('source_id')
        ->and($sourceId->ability)->toBe('updateSource')
        ->and($sourceId->column)->toBe('source')
        ->and($sourceId->fieldLabel)->toBe('requestManagement.columns.source')
        ->and($sourceId->resourceLabel)->toBe('navigation.requestManagement')
        ->and($sourceId->recordPath)->toBe('/request-management')
        ->and($sourceId->permission())->toBe('request-management.updateSource');
});

it('returns an empty array for a resource with no protected fields', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    expect($registry->forResource('users'))->toBe([]);
});

it('find() resolves a known field and returns null for an unknown one', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    expect($registry->find('request-management', 'source_id'))->toBeInstanceOf(ProtectedField::class)
        ->and($registry->find('request-management', 'does-not-exist'))->toBeNull()
        ->and($registry->find('does-not-exist', 'source_id'))->toBeNull();
});

// AC-001: the generated permission set feeds SyncPermissions's third source.
it('permissions() contains request-management.updateSource', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    expect($registry->permissions())->toContain('request-management.updateSource');
});

it('resources() lists every resource declared in config', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    expect($registry->resources())->toBe(['request-management']);
});

// AC-054 groundwork: a second protected field, on a fixture-only resource,
// is resolved the same way — proof the registry is config-driven, not
// hardcoded to request-management/source_id.
it('resolves a second, test-only protected field added to config', function (): void {
    config(['field-change-requests.resources.widgets' => [
        'record_path' => '/widgets',
        'label' => 'navigation.widgets',
        'fields' => [
            'name' => [
                'ability' => 'updateName',
                'column' => 'name',
                'label' => 'widgets.columns.name',
            ],
        ],
    ]]);

    $registry = new ProtectedFieldRegistry;

    expect($registry->find('widgets', 'name')?->permission())->toBe('widgets.updateName')
        ->and($registry->permissions())->toContain('widgets.updateName');
});

it('memoizes the built map across repeated calls', function (): void {
    $registry = app(ProtectedFieldRegistry::class);

    $first = $registry->forResource('request-management');
    $second = $registry->forResource('request-management');

    expect($second)->toBe($first);
});
