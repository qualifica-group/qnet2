<?php

use App\Jobs\GenerateExportJob;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteLineCommission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function commissionConfigurationActor(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }

    $actor = User::factory()->create();
    foreach ($abilities as $ability) {
        $actor->givePermissionTo("commission-configurations.{$ability}");
    }

    return $actor;
}

it('supports authorized CRUD with cross-field validation and protected deletion', function () {
    $actor = commissionConfigurationActor(['create', 'view', 'update', 'delete']);
    Sanctum::actingAs($actor);
    $product = Product::factory()->create();

    $response = $this->postJson('/api/commission-configurations', [
        'name' => 'Sales product rule',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT',
        'product_id' => $product->id,
        'commission_type' => 'PERCENTAGE',
        'value' => '5.5000',
        'priority' => 10,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ])->assertCreated()->assertJsonPath('data.value', '5.5000');

    $id = $response->json('data.id');
    $this->patchJson("/api/commission-configurations/{$id}", ['priority' => 20])
        ->assertOk()
        ->assertJsonPath('data.priority', 20);

    $this->postJson('/api/commission-configurations', [
        'name' => 'Invalid scope',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT',
        'product_category_id' => $product->category_id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ])->assertUnprocessable()->assertJsonValidationErrors(['product_id', 'product_category_id']);

    $configuration = CommissionConfiguration::findOrFail($id);
    QuoteLineCommission::factory()->create(['commission_configuration_id' => $configuration->id]);

    $this->deleteJson("/api/commission-configurations/{$id}")
        ->assertConflict()
        ->assertJsonPath('message', 'This commission configuration is in use and cannot be deleted.');
});

it('registers metadata, backend-driven table, search, export and activity log', function () {
    Queue::fake();
    $actor = commissionConfigurationActor(['viewAny', 'view', 'create', 'export', 'viewActivity']);
    Sanctum::actingAs($actor);
    $configuration = CommissionConfiguration::factory()->create(['name' => 'Searchable sales rule']);

    $this->getJson('/api/meta/commission-configurations')
        ->assertOk()
        ->assertJsonPath('data.fields.0.key', 'name');

    $columns = $this->getJson('/api/tables/commission-configurations/columns')->assertOk()->json('data.columns');
    expect(collect($columns)->pluck('id')->all())->toContain(
        'name', 'recipient_role', 'application_scope', 'category', 'product',
        'commission_type', 'value', 'priority', 'status', 'updated_at',
    );
    $columnsById = collect($columns)->keyBy('id');
    expect(collect($columnsById['recipient_role']['badges'])->pluck('value')->all())
        ->toBe(['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER'])
        ->and(collect($columnsById['application_scope']['badges'])->pluck('value')->all())
        // Spec 0089 D-2: application_scope grows a third value, RECIPIENT.
        ->toBe(['PRODUCT_CATEGORY', 'PRODUCT', 'RECIPIENT'])
        ->and(collect($columnsById['commission_type']['badges'])->pluck('value')->all())
        ->toBe(['FIXED_AMOUNT', 'PERCENTAGE'])
        ->and(collect($columnsById['status']['badges'])->pluck('value')->all())
        ->toBe(['ACTIVE', 'SUSPENDED'])
        ->and($columnsById['status']['badges'][0])
        ->toMatchArray(['label' => 'Active', 'color' => 'green']);

    $rows = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'Searchable',
    ])->assertOk()->assertJsonPath('pagination.total', 1)->json('items');
    expect($rows[0])->toMatchArray([
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT',
        'commission_type' => 'PERCENTAGE',
        'status' => 'ACTIVE',
    ]);

    $this->postJson('/api/exports/commission-configurations', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertCreated()->assertJsonPath('data.export_run.resource', 'commission-configurations');
    Queue::assertPushed(GenerateExportJob::class);

    $this->getJson("/api/activity-log/commission-configurations/{$configuration->id}")
        ->assertOk();
});

it('redacts hidden fields from detail and activity log responses', function () {
    foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
        Permission::findOrCreate("commission-configurations.{$ability}");
    }

    $role = Role::create(['name' => fake()->unique()->slug()]);
    $role->givePermissionTo([
        'commission-configurations.viewAny',
        'commission-configurations.view',
        'commission-configurations.viewActivity',
    ]);
    foreach (['product_id', 'valid_until', 'internal_note'] as $field) {
        $role->fieldPermissions()->create([
            'resource' => 'commission-configurations',
            'field' => $field,
            'visible' => false,
            'editable' => false,
            'required' => false,
        ]);
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);
    Sanctum::actingAs($actor);

    $configuration = CommissionConfiguration::factory()->create([
        'product_id' => Product::factory()->create()->id,
        'product_category_id' => null,
        'valid_until' => '2026-12-31',
        'internal_note' => 'Sensitive internal commission note',
    ]);

    $detail = $this->getJson("/api/commission-configurations/{$configuration->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.product_id.hidden', true)
        ->json('data');

    expect($detail)->not->toHaveKeys([
        'product_id',
        'product',
        'valid_until',
        'internal_note',
    ]);

    $columns = $this->getJson('/api/tables/commission-configurations/columns')
        ->assertOk()
        ->json('data.columns');
    expect(collect($columns)->pluck('id')->all())->not->toContain('product');

    $row = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk()->json('items.0');
    expect($row)->not->toHaveKey('product');

    $activityFields = collect(
        $this->getJson("/api/activity-log/commission-configurations/{$configuration->id}")
            ->assertOk()
            ->json('data.items'),
    )->flatMap(fn (array $activity): array => collect($activity['changes'])->pluck('field')->all());

    expect($activityFields)->not->toContain('product_id', 'valid_until', 'internal_note');
});

it('deletes an unreferenced configuration through REST and preserves the same guard in bulk delete', function () {
    $actor = commissionConfigurationActor(['viewAny', 'delete']);
    Sanctum::actingAs($actor);
    $standalone = CommissionConfiguration::factory()->create();

    $this->deleteJson("/api/commission-configurations/{$standalone->id}")
        ->assertNoContent();
    $this->assertDatabaseMissing('commission_configurations', ['id' => $standalone->id]);

    $guarded = CommissionConfiguration::factory()->create();
    QuoteLineCommission::factory()->create(['commission_configuration_id' => $guarded->id]);
    $deletable = CommissionConfiguration::factory()->create();

    $response = $this->postJson('/api/tables/commission-configurations/bulk-delete', [
        'ids' => [$guarded->id, $deletable->id],
    ])->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $guarded->id)['reason'])
        ->toBe('guarded');
    $this->assertDatabaseHas('commission_configurations', ['id' => $guarded->id]);
    $this->assertDatabaseMissing('commission_configurations', ['id' => $deletable->id]);
});

it('denies every configurator controller action without its matching ability', function () {
    $actor = commissionConfigurationActor([]);
    Sanctum::actingAs($actor);
    $configuration = CommissionConfiguration::factory()->create();
    $product = Product::factory()->create();

    $this->getJson("/api/commission-configurations/{$configuration->id}")->assertForbidden();
    $this->postJson('/api/commission-configurations', [
        'name' => 'Forbidden rule',
        'recipient_role' => 'COMMERCIAL',
        'application_scope' => 'PRODUCT',
        'product_id' => $product->id,
        'commission_type' => 'PERCENTAGE',
        'value' => 5,
        'priority' => 0,
        'valid_from' => '2026-01-01',
        'status' => 'ACTIVE',
    ])->assertForbidden();
    $this->patchJson("/api/commission-configurations/{$configuration->id}", [])->assertForbidden();
    $this->deleteJson("/api/commission-configurations/{$configuration->id}")->assertForbidden();
});

it('filters, sorts and resolves distinct values for derived category and product columns', function () {
    $actor = commissionConfigurationActor(['viewAny']);
    Sanctum::actingAs($actor);
    $alphaCategory = ProductCategory::factory()->create(['name' => 'Alpha category']);
    $zetaCategory = ProductCategory::factory()->create(['name' => 'Zeta category']);
    $alphaProduct = Product::factory()->create(['name' => 'Alpha product', 'category_id' => $alphaCategory->id]);
    $zetaProduct = Product::factory()->create(['name' => 'Zeta product', 'category_id' => $zetaCategory->id]);
    CommissionConfiguration::factory()->create([
        'name' => 'Alpha rule',
        'product_id' => $alphaProduct->id,
        'product_category_id' => null,
    ]);
    CommissionConfiguration::factory()->create([
        'name' => 'Zeta rule',
        'product_id' => $zetaProduct->id,
        'product_category_id' => null,
    ]);
    CommissionConfiguration::factory()->create([
        'name' => 'Alpha category rule',
        'application_scope' => 'PRODUCT_CATEGORY',
        'product_id' => null,
        'product_category_id' => $alphaCategory->id,
    ]);
    CommissionConfiguration::factory()->create([
        'name' => 'Zeta category rule',
        'application_scope' => 'PRODUCT_CATEGORY',
        'product_id' => null,
        'product_category_id' => $zetaCategory->id,
    ]);

    $filtered = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'category' => ['filterType' => 'set', 'values' => ['Alpha category']],
        ],
    ])->assertOk()->json('items');
    expect(collect($filtered)->pluck('name')->all())->toBe(['Alpha category rule']);

    $sorted = $this->postJson('/api/tables/commission-configurations/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'application_scope' => ['filterType' => 'set', 'values' => ['PRODUCT']],
        ],
        'sortModel' => [['colId' => 'product', 'sort' => 'desc']],
    ])->assertOk()->json('items');
    expect(collect($sorted)->pluck('product')->all())->toBe(['Zeta product', 'Alpha product']);

    $this->postJson('/api/tables/commission-configurations/values', [
        'columnId' => 'category',
        'search' => 'Alpha',
    ])->assertOk()->assertJsonPath('data.values', ['Alpha category']);
    $this->postJson('/api/tables/commission-configurations/values', [
        'columnId' => 'product',
    ])->assertOk()->assertJsonPath('data.values', ['Alpha product', 'Zeta product']);
});
