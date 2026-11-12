<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ExportRun;
use App\Models\ProductCategory;
use App\Models\UserTableFilter;
use App\Tables\Shared\PresetListFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The parent category filter stands for the linked group (spec 0374, D-12): AC-024..AC-027.

uses(RefreshDatabase::class);

it('hides the linked legacy roots from the picker but still resolves them by id (AC-024)', function () {
    $current = ProductCategory::factory()->create(['name' => 'Current']);
    $linked = categoryTabLegacyRoot($current, '[OLD] Linked');
    $loose = categoryTabLegacyRoot(null, '[OLD] Loose');
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $ids = fn (string $query): array => array_column($this->getJson("/api/product-category-roots/for-select?{$query}")->assertOk()->json('items'), 'id');

    expect($ids('hide_linked_legacy=1'))->toContain(0, $current->id, $loose->id)->not->toContain($linked->id)
        ->and($ids("hide_linked_legacy=1&ids[]={$linked->id}"))->toContain($linked->id)
        ->and($ids(''))->toContain(0, $current->id, $loose->id, $linked->id);

    $descriptor = collect($this->getJson('/api/tables/quotes/columns')->json('data.advancedFilters'))->firstWhere('name', 'parent_category');
    expect($descriptor['source']['params']['hide_linked_legacy'])->toBe(1);
});

it('lists the records of the whole group and of nothing else, rows and export (AC-025)', function (string $grid, Closure $makeRecord, string $codeColumn) {
    Storage::fake('local');
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot($current);
    $other = ProductCategory::factory()->create();
    $second = ProductCategory::factory()->create();
    $onCurrent = $makeRecord($current);
    $onLegacy = $makeRecord($legacy);
    $onOther = $makeRecord($other);
    $onSecond = $makeRecord($second);
    $actor = categoryTabGridActor($grid);
    $actor->givePermissionTo(Permission::findOrCreate("{$grid}.export", 'web'));
    Sanctum::actingAs($actor);
    $rowIds = fn (array $roots): array => array_column($this->postJson("/api/tables/{$grid}/rows", ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => $roots]])->assertOk()->json('items'), 'id');

    expect($rowIds([$current->id]))->toEqualCanonicalizing([$onCurrent->id, $onLegacy->id])
        ->and($rowIds([$legacy->id]))->toEqualCanonicalizing([$onCurrent->id, $onLegacy->id])
        ->and($rowIds([$current->id, $other->id]))->toEqualCanonicalizing([$onCurrent->id, $onLegacy->id, $onOther->id])
        ->and($rowIds([$second->id]))->toBe([$onSecond->id]);

    $response = $this->postJson("/api/exports/{$grid}", ['format' => 'csv', 'columns' => [['colId' => $codeColumn, 'header' => 'Code']], 'advancedFilters' => ['parent_category' => [$current->id]]])->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->fresh()->row_count)->toBe(2);
})->with([
    'quotes' => ['quotes', fn ($category) => categoryTabQuote([$category]), 'code'],
    'work-orders' => ['work-orders', fn ($category) => categoryTabMultiWorkOrder(categoryTabQuote([$category])), 'code'],
    'contracts' => ['contracts', fn ($category) => categoryTabContract([$category]), 'code'],
    'opportunities' => ['opportunities', fn ($category) => categoryTabOpportunity($category), 'name'],
]);

it('shows no attribute column when two different groups are selected (AC-025)', function () {
    [$current] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'r_field']);
    $legacy = categoryTabLegacyRoot($current);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'l_field'], $legacy);
    [$other] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 's_field']);
    categoryTabQuote([$current])->forceFill(['attribute_values' => ['r_field' => 'x']])->save();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $rows = fn (array $roots): array => $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => $roots]])->assertOk()->json('items.0');

    expect(array_keys($rows([$current->id])))->toContain('attr.r_field')
        ->and(array_keys($rows([$current->id, $other->id])))->not->toContain('attr.r_field');
});

// REQUIREMENT CHANGED (spec 0374 rev.5): the parent category starts empty for everyone; empty is read as the actor's
// current roots, so the rows and the columns still follow the competence, a legacy root counted as its current one.
it('reads the empty parent category as the current roots of the competence, whatever its legacy roots (AC-026)', function () {
    [$function, $current] = competenceVisibilityCategory();
    [, $legacy] = competenceVisibilityCategory($function);
    $legacy->forceFill(['is_legacy' => true, 'current_category_id' => $current->id])->save();
    $legacyOnly = competenceVisibilityActor('quotes', ['viewAny', 'view', 'viewCompetence']);
    grantCompetence($legacyOnly, $function, $legacy);
    Sanctum::actingAs($legacyOnly);
    $onCurrent = categoryTabQuote([$current]);
    $onLegacy = categoryTabQuote([$legacy]);

    $default = collect($this->getJson('/api/tables/quotes/columns')->json('data.advancedFilters'))->firstWhere('name', 'parent_category')['defaultValue'];
    $rows = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => []]])->assertOk()->json('items');

    expect($default)->toBe([])
        ->and(app(PresetListFilters::class)->effectiveRootIds([], $legacyOnly))->toBe([$current->id])
        ->and(array_column($rows, 'id'))->toBe([$onLegacy->id])
        ->and($onCurrent->id)->not->toBe($onLegacy->id);
});

it('reports the current root in the applied filters without rewriting the saved ones (AC-027)', function () {
    $current = ProductCategory::factory()->create();
    $legacy = categoryTabLegacyRoot($current);
    $loose = categoryTabLegacyRoot();
    $actor = categoryTabGridActor('quotes');
    Sanctum::actingAs($actor);
    $applied = fn (array $roots): ?array => (function () use ($roots) {
        categoryTabSaveFilters('quotes', ['parent_category' => $roots])->assertOk();

        return $this->getJson('/api/tables/quotes/columns')->json('data.appliedAdvancedFilters.parent_category');
    })();

    expect($applied([$legacy->id]))->toBe([$current->id])
        ->and(UserTableFilter::query()->where('user_id', $actor->id)->first()->advanced_filters['parent_category'])->toBe([$legacy->id])
        ->and($applied([$current->id, $legacy->id]))->toBe([$current->id])
        ->and($applied([$loose->id]))->toBe([$loose->id]);
});
