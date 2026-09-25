<?php

use App\Migrations\MigrationQuery;
use App\Migrations\Sources\RolesSource;
use App\Models\CustomFieldDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

// ---------------------------------------------------------------------------
// Spec 0021/0013 — AbstractMigrationSource generically exposes + previews a
// source's active custom fields (RolesSource's entity_type "roles" is used
// throughout: nativeColumns() = [id, name, description]).
// ---------------------------------------------------------------------------

it('is a pure passthrough on columns() when the entity_type has no active custom fields', function () {
    $columns = app(RolesSource::class)->columns();

    expect($columns)->toBe([
        ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
        ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
    ]);
});

it('appends active custom fields to columns() with the correct type mapping', function () {
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('text')->create([
        'key' => 'internal_note', 'label' => 'Internal note', 'sort_order' => 1,
    ]);
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('integer')->create([
        'key' => 'priority', 'label' => 'Priority', 'sort_order' => 2,
    ]);
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('boolean')->create([
        'key' => 'is_flagged', 'label' => 'Is flagged', 'sort_order' => 3,
    ]);
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('enum')->create([
        'key' => 'severity', 'label' => 'Severity', 'sort_order' => 4,
    ]);
    // Inactive and other-entity definitions never leak in.
    CustomFieldDefinition::factory()->forEntity('roles')->inactive()->create(['key' => 'hidden']);
    CustomFieldDefinition::factory()->forEntity('companies')->create(['key' => 'unrelated']);

    $columns = app(RolesSource::class)->columns();

    expect($columns)->toBe([
        ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
        ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
        ['id' => 'description', 'label' => 'Description', 'type' => 'string'],
        ['id' => 'internal_note', 'label' => 'Internal note', 'type' => 'string'],
        ['id' => 'priority', 'label' => 'Priority', 'type' => 'number'],
        ['id' => 'is_flagged', 'label' => 'Is flagged', 'type' => 'boolean'],
        ['id' => 'severity', 'label' => 'Severity', 'type' => 'string'],
    ]);
});

it('includes the custom-field cells (raw keys) in preview rows via mapRow()', function () {
    seedMigrationsConfig();
    CustomFieldDefinition::factory()->forEntity('roles')->ofType('text')->create([
        'key' => 'internal_note', 'label' => 'Internal note', 'sort_order' => 1,
    ]);

    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => 'operator', 'internal_note' => 'Legacy note'],
            ],
            'pagination' => ['total' => 1, 'offset' => 0, 'limit' => 50, 'total_pages' => 1],
        ]),
    ]);

    $page = app(RolesSource::class)->preview(new MigrationQuery(page: 1, perPage: 50));

    expect($page->rows)->toBe([
        ['id' => 10, 'name' => 'operator', 'description' => null, 'internal_note' => 'Legacy note'],
    ]);
});
