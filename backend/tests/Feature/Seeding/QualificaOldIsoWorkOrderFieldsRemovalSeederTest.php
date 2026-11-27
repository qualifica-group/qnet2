<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Database\Seeders\QualificaOldIsoWorkOrderFieldsRemovalSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * "[OLD] ISO" and its subcategories lose the "Dati storici ISO" section, its
 * fields and the flexible "Stato Lavorazione" from the Commessa; values and
 * other categories stay.
 */
uses(RefreshDatabase::class);

function oldIsoSection(string $id, array $codes): array
{
    return [
        'id' => $id, 'title' => $id, 'sort_order' => 0, 'variant' => 'default', 'description' => null,
        'collapsible' => false, 'default_collapsed' => false, 'visible_when' => null, 'columns' => 2,
        'rows' => [['id' => "{$id}-0", 'items' => array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'], $codes)]],
    ];
}

function oldIsoForm(ProductCategory $category): AttributeLayout
{
    return AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => AttributeContext::WorkOrder,
        'form_mode' => LayoutFormScope::All,
        'layout' => ['sections' => [oldIsoSection('iso-focus', ['scopo']), oldIsoSection('iso-dati-storici', ['settore', 'data_vi'])], 'canonical_order' => true],
    ]);
}

function oldIsoWorkOrderCodes(ProductCategory $category): array
{
    return $category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->pluck('code')->sort()->values()->all();
}

beforeEach(function (): void {
    $this->root = ProductCategory::factory()->create(['name' => QualificaOldIsoWorkOrderFieldsRemovalSeeder::ROOT, 'parent_id' => null]);
    $this->child = ProductCategory::factory()->create(['name' => '[OLD] ISO_9001', 'parent_id' => $this->root->id]);
    $this->other = ProductCategory::factory()->create(['name' => '[OLD] PROGETTI-', 'parent_id' => null]);

    [, $settore] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'settore'], $this->root);
    [, $dataVi] = categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => 'data_vi'], $this->root);
    categoryTabAttribute(AttributeContext::WorkOrder, 'textarea', ['code' => 'scopo'], $this->root);

    foreach ([$this->child, $this->other] as $category) {
        $category->attributes()->attach([$settore->id, $dataVi->id], ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 0]);
    }

    $this->root->attributes()->attach($settore->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
    $this->rootForm = oldIsoForm($this->root);
    $this->childForm = oldIsoForm($this->child);
    $this->otherForm = oldIsoForm($this->other);
});

it('drops the section and its Commessa fields from [OLD] ISO and its subcategories', function () {
    $this->seed(QualificaOldIsoWorkOrderFieldsRemovalSeeder::class);

    foreach ([$this->rootForm, $this->childForm] as $form) {
        expect(array_column($form->fresh()->layout['sections'], 'id'))->toBe(['iso-focus'])
            ->and($form->fresh()->layout['canonical_order'])->toBeTrue();
    }

    expect(oldIsoWorkOrderCodes($this->root))->toBe(['scopo'])
        ->and(oldIsoWorkOrderCodes($this->child))->toBe([])
        ->and($this->root->attributes()->wherePivot('context', AttributeContext::Quote->value)->pluck('code')->all())->toBe(['settore']);
});

it('drops the flexible state from its section, which keeps its other fields', function () {
    categoryTabAttribute(AttributeContext::WorkOrder, 'enum', ['code' => QualificaOldIsoWorkOrderFieldsRemovalSeeder::STATE_CODE], $this->root);
    $this->rootForm->update(['layout' => ['sections' => [oldIsoSection('iso-dettagli-lavorazioni-iso', [QualificaOldIsoWorkOrderFieldsRemovalSeeder::STATE_CODE, 'scopo'])]]]);
    $workOrder = WorkOrder::factory()->create(['attribute_values' => [QualificaOldIsoWorkOrderFieldsRemovalSeeder::STATE_CODE => '12']]);

    $this->seed(QualificaOldIsoWorkOrderFieldsRemovalSeeder::class);

    $section = $this->rootForm->fresh()->layout['sections'][0];

    expect($section['id'])->toBe('iso-dettagli-lavorazioni-iso')
        ->and(array_column($section['rows'][0]['items'], 'attribute_code'))->toBe(['scopo'])
        ->and(oldIsoWorkOrderCodes($this->root))->toBe(['scopo'])
        ->and($workOrder->fresh()->attribute_values)->toBe([QualificaOldIsoWorkOrderFieldsRemovalSeeder::STATE_CODE => '12']);
});

it('leaves other categories and the stored values alone, and changes nothing on a second run', function () {
    $workOrder = WorkOrder::factory()->create(['attribute_values' => ['settore' => 'EA 28', 'data_vi' => '2024-05-02']]);

    $this->seed(QualificaOldIsoWorkOrderFieldsRemovalSeeder::class);
    $afterFirst = $this->rootForm->fresh()->updated_at;
    $this->seed(QualificaOldIsoWorkOrderFieldsRemovalSeeder::class);

    expect(oldIsoWorkOrderCodes($this->other))->toBe(['data_vi', 'settore'])
        ->and(array_column($this->otherForm->fresh()->layout['sections'], 'id'))->toBe(['iso-focus', 'iso-dati-storici'])
        ->and($workOrder->fresh()->attribute_values)->toBe(['settore' => 'EA 28', 'data_vi' => '2024-05-02'])
        ->and($this->rootForm->fresh()->updated_at)->toEqual($afterFirst);
});

it('fails loudly without the [OLD] ISO root', function () {
    $this->root->update(['name' => 'ISO']);

    $this->seed(QualificaOldIsoWorkOrderFieldsRemovalSeeder::class);
})->throws(ModelNotFoundException::class);
