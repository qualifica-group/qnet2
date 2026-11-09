<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Contract;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Read-only attr.<code> columns of the contracts grid, from the source offer, driven by the category filter (spec 0374): AC-010, AC-012.

uses(RefreshDatabase::class);

/**
 * A contract in $category whose source offer carries $values.
 *
 * @param  array<string, mixed>  $values
 */
function contractAttrContract(ProductCategory $category, array $values): Contract
{
    $contract = categoryTabContract([$category]);
    $contract->quote->forceFill(['attribute_values' => $values])->save();

    return $contract;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<int, array<string, mixed>>
 */
function contractAttrRows(array $extra = []): array
{
    return test()->postJson('/api/tables/contracts/rows', ['startRow' => 0, 'endRow' => 25, ...$extra])->assertOk()->json('items');
}

it('lists the offer attributes as read-only columns of the filtered category (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata', 'name' => 'Durata']);
    Sanctum::actingAs(categoryTabGridActor('contracts'));
    categoryTabSaveFilters('contracts', categoryTabFilters($category))->assertOk();

    expect(categoryTabColumn(categoryTabConfigColumns('contracts'), 'attr.durata'))->toMatchArray(['label' => 'Durata', 'editable' => false, 'visible' => true]);
});

it('shows the offer values and filters, sorts and lists distinct values on them (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $beta = contractAttrContract($category, ['durata' => 'beta']);
    $alpha = contractAttrContract($category, ['durata' => 'alpha']);
    $empty = categoryTabContract([$category]);
    Sanctum::actingAs(categoryTabGridActor('contracts'));
    $scope = ['advancedFilters' => categoryTabFilters($category)];

    $rows = contractAttrRows($scope);
    expect(collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.durata']])->all())
        ->toBe([$beta->id => 'beta', $alpha->id => 'alpha', $empty->id => null]);

    $asc = contractAttrRows([...$scope, 'sortModel' => [['colId' => 'attr.durata', 'sort' => 'asc']]]);
    $desc = contractAttrRows([...$scope, 'sortModel' => [['colId' => 'attr.durata', 'sort' => 'desc']]]);
    expect(array_column($asc, 'id'))->toBe([$empty->id, $alpha->id, $beta->id])
        ->and(array_column($desc, 'id'))->toBe([$beta->id, $alpha->id, $empty->id]);

    $filtered = contractAttrRows([...$scope, 'filterModel' => ['attr.durata' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]]);
    expect(array_column($filtered, 'id'))->toBe([$alpha->id]);

    $values = $this->postJson('/api/tables/contracts/values', ['columnId' => 'attr.durata'])->assertOk()->json('data.values');
    expect($values)->toContain('alpha', 'beta');
});

it('paginates a sorted grid without duplicating or losing rows (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'integer', ['code' => 'n']);
    $ids = collect(range(1, 5))->map(fn (int $n): int => contractAttrContract($category, ['n' => $n])->id)->all();
    Sanctum::actingAs(categoryTabGridActor('contracts'));
    $base = ['advancedFilters' => categoryTabFilters($category), 'sortModel' => [['colId' => 'attr.n', 'sort' => 'desc']]];

    $first = $this->postJson('/api/tables/contracts/rows', [...$base, 'startRow' => 0, 'endRow' => 3])->assertOk();
    $second = $this->postJson('/api/tables/contracts/rows', [...$base, 'startRow' => 3, 'endRow' => 6])->assertOk();
    $seen = [...array_column($first->json('items'), 'id'), ...array_column($second->json('items'), 'id')];

    expect($seen)->toBe(array_reverse($ids))->and($first->json('pagination.total'))->toBe(5);
});

it('refuses the cell edit and leaves the offer untouched (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $contract = contractAttrContract($category, ['durata' => 'keep']);
    Sanctum::actingAs(contractScopedUserWith(['viewAny', 'update', 'viewAll']));

    $this->patchJson("/api/tables/contracts/rows/{$contract->id}", ['column' => 'attr.durata', 'value' => 'changed'])->assertUnprocessable();

    expect($contract->quote->fresh()->attribute_values)->toBe(['durata' => 'keep']);
});

it('lists only values of contracts the actor can see (AC-012)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    $actor = contractScopedUserWith(['viewAny', 'view']);
    $mine = contractAttrContract($category, ['durata' => 'visible-value']);
    $mine->quote->forceFill(['supervisor_id' => $actor->id])->save();
    contractAttrContract($category, ['durata' => 'hidden-value'])->quote->forceFill(['supervisor_id' => User::factory()->create()->id])->save();
    Sanctum::actingAs($actor);

    $rows = contractAttrRows(['advancedFilters' => categoryTabFilters($category)]);
    $values = $this->postJson('/api/tables/contracts/values', ['columnId' => 'attr.durata'])->assertOk()->json('data.values');

    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and($values)->toContain('visible-value')->not->toContain('hidden-value');
});

it('keeps a hidden attribute column hidden on reload (AC-013)', function () {
    [$category] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata']);
    Sanctum::actingAs(categoryTabGridActor('contracts'));

    categoryTabAssertAttributeStaysHidden('contracts', $category, 'durata');
});
