<?php

declare(strict_types=1);

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\CostType;
use App\Models\PurchaseOrderStatusLabel;
use App\Models\PurchaseRequestLineStatusLabel;
use App\Models\PurchaseRequestPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

// Spec 0258: the purchasing catalogues edit their cells through each
// catalogue's own update FormRequest + service (D-1).

uses(RefreshDatabase::class);

function purchasingCatalogEditor(string $resource, bool $canUpdate = true): User
{
    $abilities = $canUpdate ? ['viewAny', 'view', 'update'] : ['viewAny', 'view'];

    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("{$resource}.{$ability}", 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = User::factory()->create();
    $user->givePermissionTo(array_map(static fn (string $ability): string => "{$resource}.{$ability}", $abilities));

    return $user;
}

function patchCatalogCell(string $resource, int $id, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/{$resource}/rows/{$id}", ['column' => $column, 'value' => $value]);
}

/**
 * @return array<int, string>
 */
function editableCatalogColumns(string $resource): array
{
    return collect(test()->getJson("/api/tables/{$resource}/columns")->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();
}

it('AC-001: declares the editable columns of each catalogue (D-2)', function (string $resource, array $expected) {
    Sanctum::actingAs(purchasingCatalogEditor($resource));

    expect(editableCatalogColumns($resource))->toBe($expected);
})->with([
    'cost types' => ['cost-types', ['color', 'description', 'is_active', 'name', 'nature']],
    'priorities' => ['purchase-request-priorities', ['color', 'description', 'is_active', 'is_default', 'name']],
    'order statuses' => ['purchase-order-statuses', ['color', 'name']],
    'line statuses' => ['purchase-request-line-statuses', ['color', 'name']],
]);

it('AC-001: nothing is editable without update', function () {
    Sanctum::actingAs(purchasingCatalogEditor('cost-types', canUpdate: false));
    $costType = CostType::factory()->create();

    expect(editableCatalogColumns('cost-types'))->toBe([]);
    patchCatalogCell('cost-types', $costType->id, 'name', 'Nope')->assertForbidden();
});

it('AC-001: saves the cost type cells through the form rules', function () {
    Sanctum::actingAs(purchasingCatalogEditor('cost-types'));
    $costType = CostType::factory()->create(['name' => 'Consulenze']);
    $other = CostType::factory()->create(['name' => 'Materiali']);

    patchCatalogCell('cost-types', $costType->id, 'name', 'Servizi')->assertOk()->assertJsonPath('data.name', 'Servizi');
    patchCatalogCell('cost-types', $costType->id, 'description', 'Esterni')->assertOk()->assertJsonPath('data.description', 'Esterni');
    patchCatalogCell('cost-types', $costType->id, 'color', 'teal')->assertOk()->assertJsonPath('data.color', 'teal');
    patchCatalogCell('cost-types', $costType->id, 'is_active', false)->assertOk()->assertJsonPath('data.is_active', false);

    patchCatalogCell('cost-types', $costType->id, 'name', $other->name)->assertUnprocessable();
    patchCatalogCell('cost-types', $costType->id, 'color', 'not-a-color')->assertUnprocessable();
    patchCatalogCell('cost-types', $costType->id, 'nature', 'unknown')->assertUnprocessable();
});

it('AC-002: a priority made default in the cell takes the default from the previous one', function () {
    Sanctum::actingAs(purchasingCatalogEditor('purchase-request-priorities'));
    $previous = PurchaseRequestPriority::query()->where('is_default', true)->firstOrFail();
    $priority = PurchaseRequestPriority::factory()->create(['is_active' => true, 'is_default' => false]);

    patchCatalogCell('purchase-request-priorities', $priority->id, 'is_default', true)->assertOk()->assertJsonPath('data.is_default', true);
    patchCatalogCell('purchase-request-priorities', $priority->id, 'name', 'Bloccante')->assertOk()->assertJsonPath('data.name', 'Bloccante');

    expect($previous->fresh()->is_default)->toBeFalse();
});

it('AC-001: renames and recolours the order and line statuses', function () {
    $orderStatus = PurchaseOrderStatusLabel::query()->where('system_key', PurchaseOrderStatus::Draft->value)->firstOrFail();
    Sanctum::actingAs(purchasingCatalogEditor('purchase-order-statuses'));

    patchCatalogCell('purchase-order-statuses', $orderStatus->id, 'name', 'In bozza')->assertOk()->assertJsonPath('data.name', 'In bozza');
    patchCatalogCell('purchase-order-statuses', $orderStatus->id, 'color', 'teal')->assertOk()->assertJsonPath('data.color', 'teal');

    $lineStatus = PurchaseRequestLineStatusLabel::query()->where('system_key', PurchaseRequestLineStatus::Approved->value)->firstOrFail();
    Sanctum::actingAs(purchasingCatalogEditor('purchase-request-line-statuses'));

    patchCatalogCell('purchase-request-line-statuses', $lineStatus->id, 'name', 'Approvata')->assertOk()->assertJsonPath('data.name', 'Approvata');
    patchCatalogCell('purchase-request-line-statuses', $lineStatus->id, 'color', 'nope')->assertUnprocessable();
});
