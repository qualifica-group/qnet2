<?php

use App\Imports\ImportRegistry;
use App\Imports\LeadsImportDefinition;
use App\Imports\RequestManagementImportDefinition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Stubs\StubImportDefinition;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 — registry resolve + unknown domain
// ---------------------------------------------------------------------------

it('resolves a registered domain to its definition via the container (dependencies injected)', function () {
    config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);

    $definition = app(ImportRegistry::class)->resolve('stub-widgets');

    expect($definition)->toBeInstanceOf(StubImportDefinition::class)
        ->and($definition->domain())->toBe('stub-widgets')
        ->and($definition->resource())->toBe('stub-widgets');
});

it('throws ModelNotFoundException for an unregistered domain (404 via BaseApiController)', function () {
    config(['imports.definitions' => []]);

    expect(fn () => app(ImportRegistry::class)->resolve('unknown-domain'))
        ->toThrow(ModelNotFoundException::class);
});

// Requirement changed 2026-07-16: the 5 legacy domains (business-functions,
// companies, operational-sites, roles, users) are removed — they will be
// rebuilt later on the unified wizard flow. Requirement changed 2026-11-03
// (spec 0348): `request-management` joins `leads`.
it('config/imports.php registers the leads and request-management definitions', function () {
    expect(config('imports.definitions'))->toBe([
        'leads' => LeadsImportDefinition::class,
        'request-management' => RequestManagementImportDefinition::class,
    ]);
});
