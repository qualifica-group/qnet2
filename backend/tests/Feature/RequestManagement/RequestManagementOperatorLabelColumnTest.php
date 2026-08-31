<?php

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0080, AC-045 (colonna): quando il tab categoria attivo (product_category_id
// sulla query di GET .../columns, spec 0064) definisce una label per il
// livello 2 ("Operatore"/GA2), la colonna operator_ga2 la espone come TESTO
// GREZZO al posto della chiave i18n — mirror del trattamento gia' in uso per
// le colonne attr.* (AttributeColumnBuilder). id/editableField/relation/
// nullable restano invariati; cambia solo `label`.

uses(RefreshDatabase::class);

if (! function_exists('operatorLabelColumnUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function operatorLabelColumnUserWith(array $abilities): User
    {
        foreach (['viewAny', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

it('a category tab defining the level-2 label rewrites operator_ga2 to that raw text', function (): void {
    $actor = operatorLabelColumnUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore Tecnico']]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    $operator = $columns['operator_ga2'];

    // Resolved-config shape (GET .../columns): `editableField`/`nullable` are
    // RAW-catalog-only keys, never exposed here (ResolvesColumnConfig) — the
    // structural identity that DOES survive is `relation`, unchanged.
    expect($operator['label'])->toBe('Operatore Tecnico')
        ->and($operator['relation']['resource'])->toBe('users')
        ->and($operator['relation']['scope'])->toBe(['operational_site_id' => 'operational_site']);
});

it('a category with a level-2 label but ZERO attributes still relabels operator_ga2 (independent configs, no early-return skip)', function (): void {
    // Attributes and manager labels are independent configs — resolveConfig()
    // has an early return when the scoped category has no attr.* columns to
    // append; the relabel must NOT be gated behind that branch.
    $actor = operatorLabelColumnUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Solo Etichetta']]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'));

    $attrColumns = $columns->filter(fn (array $column): bool => str_starts_with($column['id'], 'attr.'));
    expect($attrColumns)->toBeEmpty();

    expect($columns->firstWhere('id', 'operator_ga2')['label'])->toBe('Solo Etichetta');
});

it('the "Tutte" tab (no product_category_id) leaves operator_ga2 on the i18n key', function (): void {
    $actor = operatorLabelColumnUserWith(['viewAny', 'viewAll']);
    ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore Tecnico']]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator');
});

it('a scoped category with no level-2 label leaves operator_ga2 on the i18n key', function (): void {
    $actor = operatorLabelColumnUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['1' => 'Commerciale']]);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns['operator_ga2']['label'])->toBe('requestManagement.columns.operator');
});

it('the relabel does not disturb attr.* columns or any other native column', function (): void {
    $actor = operatorLabelColumnUserWith(['viewAny', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Consulente']]);
    Sanctum::actingAs($actor);

    $before = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id')->except('operator_ga2');

    $after = collect($this->getJson("/api/tables/request-management/columns?product_category_id={$category->id}")
        ->assertOk()->json('data.columns'))->keyBy('id')->except('operator_ga2');

    expect($after->all())->toBe($before->all());
});

it('inline PATCH on the operator column is unaffected — the structural lookup carries no category scope', function (): void {
    $actor = operatorLabelColumnUserWith(['viewAny', 'update', 'viewAll']);
    $category = ProductCategory::factory()->create(['manager_labels' => ['2' => 'Operatore Tecnico']]);
    $opportunity = Opportunity::factory()->create();
    OpportunityProductLine::factory()->for($opportunity)->create(['product_category_id' => $category->id]);
    $quote = Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);
    $newOperator = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$quote->id}", [
        'column' => 'operator_ga2',
        'value' => $newOperator->id,
    ])->assertOk();

    // Spec 0087, D-9/D-14/AC-017: the inline editor writes `operator_id`
    // only — `supervisor_id` (the commission-recipient column) is never
    // touched by this write path any more.
    expect($quote->fresh()->operator_id)->toBe($newOperator->id)
        ->and($quote->fresh()->supervisor_id)->toBeNull();
});
