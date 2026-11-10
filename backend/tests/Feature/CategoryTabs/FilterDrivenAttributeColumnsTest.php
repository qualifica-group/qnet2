<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Enums\ExportFormat;
use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// The flexible columns follow the category the advanced filters select (spec 0374): AC-002..AC-005, AC-007, AC-011, AC-013, AC-014.

uses(RefreshDatabase::class);

/**
 * Two roots, each with one Quote-context attribute: `r_field` on R, `s_field` on S.
 *
 * @return array{0: ProductCategory, 1: ProductCategory}
 */
function filterDrivenRoots(): array
{
    [$root] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'r_field', 'name' => 'Campo R']);
    [$other] = categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 's_field', 'name' => 'Campo S']);

    return [$root, $other];
}

it('lists the effective attributes of the saved parent category, visible and after the natives (AC-002)', function () {
    [$root, $child] = categoryTabTree();
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'durata', 'name' => 'Durata'], $root);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'child_only'], $child);
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    categoryTabSaveFilters('quotes', ['parent_category' => [$root->id]])->assertOk();

    $response = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();
    $columns = $response->json('data.columns');
    $ids = array_column($columns, 'id');

    expect(filterDrivenAttrIds($columns))->toBe(['attr.durata'])
        ->and(categoryTabColumn($columns, 'attr.durata'))->toMatchArray(['label' => 'Durata', 'visible' => true])
        ->and(array_search('attr.durata', $ids, true))->toBe(count($ids) - 1)
        ->and($response->json('data.attributeCategoryIds'))->toBe([$root->id]);
});

it('opens on the competence root alone and on none with two roots or a wildcard (AC-003)', function () {
    [$function, $root] = competenceVisibilityCategory();
    [, $second] = competenceVisibilityCategory($function);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'r_field'], $root);
    $single = competenceVisibilityActor('quotes', ['viewAny', 'viewAll']);
    grantCompetence($single, $function, $root);
    $double = competenceVisibilityActor('quotes', ['viewAny', 'viewAll']);
    grantCompetence($double, $function, $root, $second);
    $wildcard = competenceVisibilityActor('quotes', ['viewAny', 'viewAll']);
    grantCompetenceOnAllCategories($wildcard);

    Sanctum::actingAs($single);
    $one = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();
    expect(filterDrivenAttrIds($one->json('data.columns')))->toBe(['attr.r_field'])
        ->and($one->json('data.attributeCategoryIds'))->toBe([$root->id]);

    foreach ([$double, $wildcard] as $actor) {
        Sanctum::actingAs($actor);
        $none = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();

        expect(filterDrivenAttrIds($none->json('data.columns')))->toBe([])
            ->and($none->json('data.attributeCategoryIds'))->toBe([]);
    }
});

it('adds no attribute column to the config without the parameter (AC-004)', function () {
    [$root] = filterDrivenRoots();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    categoryTabSaveFilters('quotes', ['parent_category' => [$root->id]])->assertOk();

    $response = $this->getJson('/api/tables/quotes/columns')->assertOk();

    expect(filterDrivenAttrIds($response->json('data.columns')))->toBe([])
        ->and($response->json('data.attributeCategoryIds'))->toBe([]);
    $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=maybe')->assertUnprocessable();
});

it('returns the columns of the category just saved and keeps the other filters stored (AC-005)', function () {
    [$root, $other] = filterDrivenRoots();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $flag = ['attribute_columns_from_filters' => 1];
    $attrFilter = ['attr.r_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']];

    $onRoot = categoryTabSaveFilters('quotes', ['parent_category' => [$root->id]], ['filterModel' => $attrFilter, ...$flag])->assertOk();
    expect(filterDrivenAttrIds($onRoot->json('data.columns')))->toBe(['attr.r_field'])
        ->and($onRoot->json('data.attributeCategoryIds'))->toBe([$root->id])
        ->and(array_keys($onRoot->json('data.filterState')))->toBe(['attr.r_field']);

    $onOther = categoryTabSaveFilters('quotes', ['parent_category' => [$other->id]], $flag)->assertOk();
    expect(filterDrivenAttrIds($onOther->json('data.columns')))->toBe(['attr.s_field'])
        ->and($onOther->json('data.attributeCategoryIds'))->toBe([$other->id])
        ->and($onOther->json('data.filterState'))->toBe([]);

    foreach ([[0], [$root->id, $other->id]] as $picked) {
        $many = categoryTabSaveFilters('quotes', ['parent_category' => $picked], $flag)->assertOk();

        expect(filterDrivenAttrIds($many->json('data.columns')))->toBe([])
            ->and($many->json('data.attributeCategoryIds'))->toBe([]);
    }

    $back = categoryTabSaveFilters('quotes', ['parent_category' => [$root->id]], $flag)->assertOk();
    expect(array_keys($back->json('data.filterState')))->toBe(['attr.r_field']);
});

it('ignores a filter and a sort on an attribute outside the category and rejects unknown ones (AC-007)', function () {
    [$root] = filterDrivenRoots();
    categoryTabQuote([$root])->forceFill(['attribute_values' => ['r_field' => 'kept']])->save();
    categoryTabQuote([$root]);
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => categoryTabFilters($root)];

    $response = $this->postJson('/api/tables/quotes/rows', [
        ...$page,
        'filterModel' => ['attr.s_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'nothing']],
        'sortModel' => [['colId' => 'attr.s_field', 'sort' => 'asc']],
    ])->assertOk();

    expect($response->json('items'))->toHaveCount(2)
        ->and(array_keys($response->json('items.0')))->not->toContain('attr.s_field');
    $this->postJson('/api/tables/quotes/rows', [...$page, 'sortModel' => [['colId' => 'attr.unknown', 'sort' => 'asc']]])->assertUnprocessable();
    $this->postJson('/api/tables/quotes/rows', [...$page, 'filterModel' => ['attr.unknown' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']]])->assertUnprocessable();
});

it('returns no attribute key when the filters select no single category (AC-006)', function () {
    [$root] = filterDrivenRoots();
    categoryTabQuote([$root])->forceFill(['attribute_values' => ['r_field' => 'a']])->save();
    Sanctum::actingAs(categoryTabGridActor('quotes'));

    $rows = $this->postJson('/api/tables/quotes/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items');

    expect($rows)->toHaveCount(1)->and(array_keys($rows[0]))->not->toContain('attr.r_field');
});

it('keeps the attribute columns of the category in the config returned by the preferences (AC-013)', function () {
    [$root] = filterDrivenRoots();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    categoryTabSaveFilters('quotes', categoryTabFilters($root))->assertOk();

    $saved = $this->postJson('/api/tables/quotes/preferences', [
        'columns' => [['id' => 'attr.r_field', 'visible' => false, 'order' => 1]],
        'attribute_columns_from_filters' => 1,
    ])->assertOk();
    $without = $this->postJson('/api/tables/quotes/preferences', ['columns' => [['id' => 'attr.r_field', 'visible' => false, 'order' => 1]]])->assertOk();

    expect(categoryTabColumn($saved->json('data.columns'), 'attr.r_field')['visible'])->toBeFalse()
        ->and(filterDrivenAttrIds($without->json('data.columns')))->toBe([]);
});

it('exports the attribute values and honours a filter and a sort on them (AC-011)', function (string $grid, Closure $makeRecord) {
    Storage::fake('local');
    [$root] = filterDrivenRoots();
    $makeRecord($root, 'beta');
    $makeRecord($root, 'alpha');
    $actor = categoryTabGridActor($grid);
    $actor->givePermissionTo(Permission::findOrCreate("{$grid}.export", 'web'));
    Sanctum::actingAs($actor);
    $payload = fn (array $extra): array => [
        'format' => 'csv',
        'columns' => [['colId' => 'attr.r_field', 'header' => 'R']],
        'advancedFilters' => categoryTabFilters($root),
        ...$extra,
    ];
    $exported = function (array $extra) use ($grid, $payload): array {
        $response = $this->postJson("/api/exports/{$grid}", $payload($extra))->assertCreated();
        $run = ExportRun::findOrFail($response->json('data.export_run.id'));

        return array_column(array_slice(categoryTabCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 1), 0);
    };

    expect($exported(['sortModel' => [['colId' => 'attr.r_field', 'sort' => 'asc']]]))->toBe(['alpha', 'beta'])
        ->and($exported(['filterModel' => ['attr.r_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'bet']]]))->toBe(['beta']);
    $this->postJson("/api/exports/{$grid}", $payload(['sortModel' => [['colId' => 'attr.unknown', 'sort' => 'asc']]]))->assertUnprocessable();
})->with([
    'quotes' => ['quotes', fn ($root, string $value) => tap(categoryTabQuote([$root]), fn ($q) => $q->forceFill(['attribute_values' => ['r_field' => $value]])->save())],
    'contracts' => ['contracts', function ($root, string $value) {
        $contract = categoryTabContract([$root]);
        $contract->quote->forceFill(['attribute_values' => ['r_field' => $value]])->save();

        return $contract;
    }],
]);

it('generates the export of the work orders from the saved state without an authenticated user (AC-011)', function () {
    Storage::fake('local');
    [$root] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$root]));
    $workOrder->forceFill(['attribute_values' => ['wo_field' => 'from-job']])->save();
    $actor = categoryTabGridActor('work-orders');
    $run = ExportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'work-orders',
        'format' => ExportFormat::Csv,
        'state' => ['columns' => [['colId' => 'attr.wo_field', 'header' => 'WO']], 'advancedFilters' => categoryTabFilters($root)],
    ]);
    Auth::forgetGuards();

    (new GenerateExportJob($run->id))->handle(app(ExportService::class));

    expect(array_column(array_slice(categoryTabCsvRows(Storage::disk('local')->get($run->fresh()->file_path)), 1), 0))->toBe(['from-job']);
});

it('adds no attribute column to opportunities and to the project work orders (AC-014)', function (string $grid, string $permissionGrid) {
    filterDrivenRoots();
    Sanctum::actingAs(categoryTabGridActor($permissionGrid));

    $response = $this->getJson("/api/tables/{$grid}/columns?attribute_columns_from_filters=1")->assertOk();

    expect(filterDrivenAttrIds($response->json('data.columns')))->toBe([])
        ->and($response->json('data.attributeCategoryIds'))->toBeNull();
})->with([
    'opportunities' => ['opportunities', 'opportunities'],
    'project-work-orders' => ['project-work-orders', 'work-orders'],
]);

it('does not take the category from another user\'s saved filters (AC-002)', function () {
    [$root] = filterDrivenRoots();
    Sanctum::actingAs(categoryTabGridActor('quotes'));
    categoryTabSaveFilters('quotes', ['parent_category' => [$root->id]])->assertOk();
    Sanctum::actingAs(User::factory()->create()->givePermissionTo(Permission::findOrCreate('quotes.viewAny', 'web')));

    $response = $this->getJson('/api/tables/quotes/columns?attribute_columns_from_filters=1')->assertOk();

    expect($response->json('data.attributeCategoryIds'))->toBe([]);
});
