<?php

use App\Services\ApiDocs\Docs\ApiModuleDescriptions;
use App\Services\ApiDocs\Docs\ApiModuleTagsTransformer;
use App\Services\ApiDocs\Docs\ApiOperationDescriptionsTransformer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, array<string, mixed>> operations keyed by "METHOD path"
 */
function apiDocOperations(): array
{
    $operations = [];

    foreach (generatedApiDocument()['paths'] as $path => $item) {
        foreach ($item as $method => $operation) {
            if (is_array($operation) && isset($operation['responses'])) {
                $operations[strtoupper($method).' '.$path] = $operation;
            }
        }
    }

    return $operations;
}

it('opens the document with the usage guide an agent needs', function () {
    $guide = generatedApiDocument()['info']['description'];

    expect($guide)
        ->toContain('## Authentication')
        ->toContain('POST /api/auth/client-login')
        ->toContain('## Listing, searching and filtering records')
        ->toContain('POST /api/tables/{domain}/rows')
        ->toContain('## Errors')
        ->toContain('for-select')
        ->not->toContain('__MAX_ROWS__');
});

it('tags every operation with its module, the first path segment', function () {
    foreach (apiDocOperations() as $key => $operation) {
        $path = explode(' ', $key, 2)[1];

        expect($operation['tags'] ?? [])->toBe([ApiModuleTagsTransformer::moduleOf($path)], $key);
    }
});

it('describes every module and keeps no description of a module that no longer exists', function () {
    $tags = collect(generatedApiDocument()['tags']);
    $modules = $tags->pluck('name')->all();

    $undescribed = array_values(array_diff($modules, array_keys(ApiModuleDescriptions::DESCRIPTIONS)));
    $stale = array_values(array_diff(array_keys(ApiModuleDescriptions::DESCRIPTIONS), $modules));

    expect($undescribed)->toBe([], 'Add these modules to ApiModuleDescriptions')
        ->and($stale)->toBe([], 'Remove these entries from ApiModuleDescriptions');

    $tags->each(fn (array $tag) => expect($tag['description'] ?? '')->not->toBe('', $tag['name']));
});

it('points a table-backed module to the endpoint that lists its records', function () {
    $leads = collect(generatedApiDocument()['tags'])->firstWhere('name', 'leads');

    expect($leads['description'])->toContain('POST /api/tables/leads/rows');
});

it('describes every operation from its module file and keeps no entry of a removed operation', function () {
    $operations = apiDocOperations();
    $described = [];

    foreach (glob(resource_path(ApiOperationDescriptionsTransformer::DIRECTORY.'/*.php')) as $file) {
        $module = basename($file, '.php');

        foreach (ApiOperationDescriptionsTransformer::forModule($module) as $key => $entry) {
            expect(ApiModuleTagsTransformer::moduleOf(explode(' ', $key, 2)[1]))->toBe($module, "{$key} is in {$module}.php");
            expect(trim($entry['summary'] ?? ''))->not->toBe('', "{$key} summary")
                ->and(trim($entry['description'] ?? ''))->not->toBe('', "{$key} description");
            $described[] = $key;
        }
    }

    expect(array_values(array_diff(array_keys($operations), $described)))->toBe([], 'Describe these operations')
        ->and(array_values(array_diff($described, array_keys($operations))))->toBe([], 'Remove these entries');

    foreach ($operations as $key => $operation) {
        $entry = ApiOperationDescriptionsTransformer::forModule($operation['tags'][0])[$key];

        expect($operation['summary'])->toBe($entry['summary'])
            ->and($operation['description'])->toBe($entry['description']);
    }
});
