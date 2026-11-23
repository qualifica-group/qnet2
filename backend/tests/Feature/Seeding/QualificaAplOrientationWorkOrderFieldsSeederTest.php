<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use Database\Seeders\QualificaAplOrientationWorkOrderFieldsSeeder;
use Database\Seeders\QualificaCatalog\AplOrientationAttributeCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The Orientamento specialistico offer fields move to the Commessa, with the
 * offer's form; the existing work orders take their offer's values.
 * REQUIREMENT CHANGED (spec 0425, D-9): the offer keeps "Anagrafica Utente",
 * synced with the Commessa, whose value it imposes on every work order.
 */
uses(RefreshDatabase::class);

const APL_ORIENTATION_SECTION_FLAGS = ['variant' => 'default', 'description' => null, 'collapsible' => false, 'default_collapsed' => false, 'visible_when' => null];

beforeEach(function (): void {
    $this->category = ProductCategory::factory()->create(['name' => AplOrientationAttributeCatalogue::CATEGORY]);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'orientation_measure_note'], $this->category);
    categoryTabAttribute(AttributeContext::Quote, 'date', ['code' => 'orientation_intake_date'], $this->category);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'user_registry'], $this->category);
    AttributeLayout::query()->create([
        'product_category_id' => $this->category->id,
        'context' => AttributeContext::Quote,
        'form_mode' => LayoutFormScope::All,
        'layout' => ['sections' => [[
            'id' => 'apl-orientation-path', 'title' => 'Percorso', 'sort_order' => 0, 'columns' => 2, ...APL_ORIENTATION_SECTION_FLAGS,
            'rows' => [['id' => 'apl-orientation-path-0', 'items' => [
                ['attribute_code' => 'orientation_measure_note', 'width' => 'half'],
                ['attribute_code' => 'orientation_intake_date', 'width' => 'half'],
            ]]],
        ]]],
    ]);

    $this->quote = categoryTabQuote([$this->category]);
    $this->quote->forceFill(['attribute_values' => ['orientation_measure_note' => 'Presa in carico', 'orientation_intake_date' => '2026-11-02', 'user_registry' => '42']])->saveQuietly();
});

function aplOrientationWorkOrder(Quote $quote, array $values = [], int $renewal = 0): WorkOrder
{
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($quote->offerLines()->sole()->id, ['renewal_number' => $renewal]);
    $workOrder->forceFill(['attribute_values' => $values])->saveQuietly();

    return $workOrder;
}

it('moves every offer field and the offer form to the Commessa, the offer keeping "Anagrafica Utente" synced', function () {
    $this->seed(QualificaAplOrientationWorkOrderFieldsSeeder::class);

    $codesIn = fn (AttributeContext $context): array => $this->category->attributes()->wherePivot('context', $context->value)->pluck('code')->sort()->values()->all();
    $syncedIn = $this->category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->get()
        ->filter(fn ($attribute): bool => (bool) $attribute->pivot->syncs_with_quote)->pluck('code')->all();
    $forms = AttributeLayout::query()->where('product_category_id', $this->category->id)->get();

    expect($codesIn(AttributeContext::WorkOrder))->toBe(['orientation_intake_date', 'orientation_measure_note', 'user_registry'])
        ->and($codesIn(AttributeContext::Quote))->toBe(['user_registry'])
        ->and($syncedIn)->toBe(['user_registry'])
        ->and($forms->pluck('context')->all())->toBe([AttributeContext::WorkOrder])
        ->and($forms->sole()->form_mode)->toBe(LayoutFormScope::All)
        ->and(collect($forms->sole()->layout['sections'][0]['rows'][0]['items'])->pluck('attribute_code')->all())
        ->toBe(['orientation_measure_note', 'orientation_intake_date']);

    // The values stay on the offer, for the work orders generated later.
    expect($this->quote->fresh()->attribute_values)->toHaveKey('orientation_measure_note');
});

it('fills the existing work orders from their offer without overwriting a value, except the synced one', function () {
    $empty = aplOrientationWorkOrder($this->quote);
    $written = aplOrientationWorkOrder($this->quote, ['orientation_measure_note' => 'Scritto a mano', 'user_registry' => '7'], 1);

    $this->seed(QualificaAplOrientationWorkOrderFieldsSeeder::class);

    expect($empty->fresh()->attribute_values)->toEqual(['orientation_measure_note' => 'Presa in carico', 'orientation_intake_date' => '2026-11-02', 'user_registry' => '42'])
        ->and($written->fresh()->attribute_values)->toEqual(['orientation_measure_note' => 'Scritto a mano', 'orientation_intake_date' => '2026-11-02', 'user_registry' => '42']);
});

it('puts the offer form over the catalogue one and changes nothing on a second run', function () {
    $workOrder = aplOrientationWorkOrder($this->quote);
    $catalogueForm = ['sections' => [[
        'id' => 'catalogue', 'title' => 'Percorso', 'sort_order' => 0, 'columns' => 1, ...APL_ORIENTATION_SECTION_FLAGS,
        'rows' => [['id' => 'catalogue-0', 'items' => [['attribute_code' => 'orientation_session_date', 'width' => 'full']]]],
    ]]];
    categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => 'orientation_session_date'], $this->category);
    AttributeLayout::query()->create(['product_category_id' => $this->category->id, 'context' => AttributeContext::WorkOrder, 'form_mode' => LayoutFormScope::All, 'layout' => $catalogueForm]);

    $this->seed(QualificaAplOrientationWorkOrderFieldsSeeder::class);
    $formAfterFirst = AttributeLayout::query()->where('product_category_id', $this->category->id)->sole()->layout;
    $afterFirst = $workOrder->fresh()->updated_at;
    $this->seed(QualificaAplOrientationWorkOrderFieldsSeeder::class);

    expect($this->category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->count())->toBe(4)
        ->and($formAfterFirst['sections'][0]['id'])->toBe('apl-orientation-path')
        ->and(AttributeLayout::query()->where('product_category_id', $this->category->id)->sole()->layout)->toBe($formAfterFirst)
        ->and($workOrder->fresh()->updated_at->equalTo($afterFirst))->toBeTrue();
});

it('gives "Anagrafica Utente" back to the offer where the previous revision already withdrew it', function () {
    $workOrder = aplOrientationWorkOrder($this->quote);
    // The 1f04e9cd7 state: every field in the Commessa only, the values still on the offer.
    $ids = $this->category->attributes()->wherePivot('context', AttributeContext::Quote->value)->pluck('attributes.id')->all();
    foreach ($ids as $id) {
        $this->category->attributes()->attach($id, ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 0]);
    }
    $this->category->attributes()->wherePivot('context', AttributeContext::Quote->value)->detach();
    AttributeLayout::query()->where('product_category_id', $this->category->id)->update(['context' => AttributeContext::WorkOrder]);

    $this->seed(QualificaAplOrientationWorkOrderFieldsSeeder::class);

    expect($this->category->attributes()->wherePivot('context', AttributeContext::Quote->value)->pluck('code')->all())->toBe(['user_registry'])
        ->and($workOrder->fresh()->attribute_values['user_registry'])->toBe('42');
});
