<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ExportRun;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Linked categories show their flexible columns together (spec 0374, D-9, D-10): AC-003, AC-020.

uses(RefreshDatabase::class);

/**
 * A current root R and a legacy root L linked to it; `shared` is assigned to both.
 *
 * @return array{0: ProductCategory, 1: ProductCategory}
 */
function linkedRoots(): array
{
    [$current] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'r_field']);
    [, $shared] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'shared'], $current);
    $legacy = categoryTabLegacyRoot($current);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'l_field'], $legacy);
    $legacy->attributes()->attach($shared->id, ['is_required' => false, 'sort_order' => 0, 'context' => AttributeContext::Quote->value]);

    return [$current, $legacy];
}

it('opens on the columns of two linked competence roots (AC-003)', function () {
    [$function, $current] = competenceVisibilityCategory();
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'r_field'], $current);
    [, $legacy] = competenceVisibilityCategory($function);
    $legacy->forceFill(['is_legacy' => true, 'current_category_id' => $current->id])->save();
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'l_field'], $legacy);
    $actor = competenceVisibilityActor('quotes', ['viewAny', 'viewAll']);
    grantCompetence($actor, $function, $legacy, $current);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();

    expect(filterDrivenAttrIds($response->json('data.columns')))->toBe(['attr.r_field', 'attr.l_field'])
        ->and($response->json('data.attributeCategoryIds'))->toBe([$current->id, $legacy->id]);
});

it('lists the current columns then the legacy ones, deduplicated by code (AC-020)', function () {
    [$current, $legacy] = linkedRoots();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    categoryTabSaveFilters('quotes', ['parent_category' => [$legacy->id, $current->id]])->assertOk();

    $response = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();

    expect(filterDrivenAttrIds($response->json('data.columns')))->toBe(['attr.r_field', 'attr.shared', 'attr.l_field'])
        ->and($response->json('data.attributeCategoryIds'))->toBe([$current->id, $legacy->id]);
});

it('reads, filters, sorts, lists distinct values and refuses a foreign write on each category (AC-020)', function () {
    [$current, $legacy] = linkedRoots();
    $onCurrent = categoryTabQuote([$current]);
    $onCurrent->forceFill(['attribute_values' => ['r_field' => 'from-current']])->save();
    $onLegacy = categoryTabQuote([$legacy]);
    $onLegacy->forceFill(['attribute_values' => ['l_field' => 'from-legacy']])->save();
    Sanctum::actingAs(quoteTableUserWith(['viewAny', 'view', 'viewAll', 'update']));
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => [$current->id, $legacy->id]]];

    $rows = collect($this->postJson('/api/tables/quotes/rows', $page)->assertOk()->json('items'))->keyBy('id');
    expect($rows[$onCurrent->id]['attr.r_field'])->toBe('from-current')
        ->and($rows[$onLegacy->id]['attr.l_field'])->toBe('from-legacy');

    $byLegacy = $this->postJson('/api/tables/quotes/rows', [...$page, 'filterModel' => ['attr.l_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'legacy']]])->assertOk();
    expect(array_column($byLegacy->json('items'), 'id'))->toBe([$onLegacy->id]);
    $this->postJson('/api/tables/quotes/rows', [...$page, 'sortModel' => [['colId' => 'attr.r_field', 'sort' => 'desc']]])->assertOk();

    foreach (['attr.r_field' => 'from-current', 'attr.l_field' => 'from-legacy'] as $columnId => $value) {
        expect($this->postJson('/api/tables/quotes/values', ['columnId' => $columnId])->assertOk()->json('data.values'))->toContain($value);
    }

    $this->patchJson("/api/tables/quotes/rows/{$onCurrent->id}", ['column' => 'attr.l_field', 'value' => 'x'])->assertUnprocessable();
    expect($onCurrent->fresh()->attribute_values)->toBe(['r_field' => 'from-current']);
    $this->patchJson("/api/tables/quotes/rows/{$onCurrent->id}", ['column' => 'attr.r_field', 'value' => 'edited'])->assertOk();
});

it('exports the columns of both categories (AC-020)', function () {
    Storage::fake('local');
    [$current, $legacy] = linkedRoots();
    categoryTabQuote([$current])->forceFill(['attribute_values' => ['r_field' => 'from-current']])->save();
    categoryTabQuote([$legacy])->forceFill(['attribute_values' => ['l_field' => 'from-legacy']])->save();
    $actor = categoryTabGridActor('quotes');
    $actor->givePermissionTo(Permission::findOrCreate('quotes.export', 'web'));
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/quotes', [
        'format' => 'csv',
        'columns' => [['colId' => 'attr.r_field', 'header' => 'R'], ['colId' => 'attr.l_field', 'header' => 'L']],
        'advancedFilters' => ['parent_category' => [$current->id, $legacy->id]],
        'sortModel' => [['colId' => 'attr.l_field', 'sort' => 'asc']],
    ])->assertCreated();
    $run = ExportRun::findOrFail($response->json('data.export_run.id'));

    $rows = categoryTabCsvRows(Storage::disk('local')->get($run->fresh()->file_path));
    expect(array_slice($rows, 1))->toEqualCanonicalizing([['from-current', ''], ['', 'from-legacy']]);
});
