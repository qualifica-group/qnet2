<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Database\Seeders\QualificaAvvalimentiTenderFieldsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * "Gare - Avvalimenti" takes the Offerta and Commessa fields of "[OLD]
 * Avvalimenti_AVV", with their forms; the legacy category keeps them.
 */
uses(RefreshDatabase::class);

const AVV_TENDER_SECTION_FLAGS = ['variant' => 'default', 'description' => null, 'collapsible' => false, 'default_collapsed' => false, 'visible_when' => null, 'columns' => 2];

function avvTenderSection(string $id, int $sortOrder, array $codes): array
{
    return [
        'id' => $id, 'title' => $id, 'sort_order' => $sortOrder, ...AVV_TENDER_SECTION_FLAGS,
        'rows' => [['id' => "{$id}-0", 'items' => array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'half'], $codes)]],
    ];
}

function avvTenderForm(ProductCategory $category, AttributeContext $context, array $sections): void
{
    AttributeLayout::query()->create(['product_category_id' => $category->id, 'context' => $context, 'form_mode' => LayoutFormScope::All, 'layout' => ['sections' => $sections]]);
}

function avvTenderCodes(ProductCategory $category, AttributeContext $context): array
{
    return $category->attributes()->wherePivot('context', $context->value)->orderBy('attribute_category.sort_order')->pluck('code')->all();
}

function avvTenderSectionIds(ProductCategory $category, AttributeContext $context): array
{
    $layout = AttributeLayout::query()->where('product_category_id', $category->id)->where('context', $context->value)->value('layout');

    return array_column($layout['sections'] ?? [], 'id');
}

beforeEach(function (): void {
    $this->source = ProductCategory::factory()->create(['id' => QualificaAvvalimentiTenderFieldsSeeder::SOURCE_ID, 'name' => QualificaAvvalimentiTenderFieldsSeeder::SOURCE_NAME]);
    $this->target = ProductCategory::factory()->create(['id' => QualificaAvvalimentiTenderFieldsSeeder::TARGET_ID, 'name' => QualificaAvvalimentiTenderFieldsSeeder::TARGET_NAME]);

    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'tender_attachments'], $this->target);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'oggetto_gara'], $this->source);
    categoryTabAttribute(AttributeContext::Quote, 'date', ['code' => 'scadenza'], $this->source);
    categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'avv_protocollo'], $this->source);
    categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'avv_cig'], $this->source);
    categoryTabAttribute(AttributeContext::Product, 'text', ['code' => 'avv_product_note'], $this->source);
    $this->source->attributes()->wherePivot('context', AttributeContext::Quote->value)->updateExistingPivot(
        $this->source->attributes()->where('code', 'scadenza')->value('attributes.id'),
        ['is_required' => true, 'sort_order' => 1],
    );
    $this->source->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->updateExistingPivot(
        $this->source->attributes()->where('code', 'avv_cig')->value('attributes.id'),
        ['sort_order' => 1],
    );

    avvTenderForm($this->target, AttributeContext::Quote, [avvTenderSection('tender-documents', 0, ['tender_attachments'])]);
    avvTenderForm($this->source, AttributeContext::Quote, [avvTenderSection('quote-avvalimenti', 500, ['oggetto_gara', 'scadenza'])]);
    avvTenderForm($this->source, AttributeContext::WorkOrder, [
        avvTenderSection('avv-gara', 510, ['avv_cig']),
        avvTenderSection('avv-info-lavorazione', 500, ['avv_protocollo']),
    ]);
});

it('copies the Offerta and Commessa fields after the target ones, the legacy category keeping them', function () {
    $this->seed(QualificaAvvalimentiTenderFieldsSeeder::class);

    $scadenza = $this->target->attributes()->wherePivot('context', AttributeContext::Quote->value)->where('code', 'scadenza')->sole();

    expect(avvTenderCodes($this->target, AttributeContext::Quote))->toBe(['tender_attachments', 'oggetto_gara', 'scadenza'])
        ->and(avvTenderCodes($this->target, AttributeContext::WorkOrder))->toBe(['avv_protocollo', 'avv_cig'])
        ->and(avvTenderCodes($this->target, AttributeContext::Product))->toBe([])
        ->and((bool) $scadenza->pivot->is_required)->toBeTrue()
        ->and(avvTenderCodes($this->source, AttributeContext::Quote))->toBe(['oggetto_gara', 'scadenza'])
        ->and(avvTenderCodes($this->source, AttributeContext::WorkOrder))->toBe(['avv_protocollo', 'avv_cig']);
});

it('appends the source form sections after the target ones, in their order', function () {
    $this->seed(QualificaAvvalimentiTenderFieldsSeeder::class);

    $quoteForm = AttributeLayout::query()->where('product_category_id', $this->target->id)->where('context', AttributeContext::Quote->value)->sole()->layout;

    expect(avvTenderSectionIds($this->target, AttributeContext::Quote))->toBe(['tender-documents', 'quote-avvalimenti'])
        ->and(array_column($quoteForm['sections'], 'sort_order'))->toBe([0, 1])
        ->and(avvTenderSectionIds($this->target, AttributeContext::WorkOrder))->toBe(['avv-info-lavorazione', 'avv-gara'])
        ->and(avvTenderSectionIds($this->source, AttributeContext::WorkOrder))->toBe(['avv-gara', 'avv-info-lavorazione']);
});

it('changes nothing on a second run', function () {
    $this->seed(QualificaAvvalimentiTenderFieldsSeeder::class);
    $forms = AttributeLayout::query()->where('product_category_id', $this->target->id)->orderBy('id')->pluck('layout')->all();
    $fields = $this->target->attributes()->count();

    $this->seed(QualificaAvvalimentiTenderFieldsSeeder::class);

    expect($this->target->attributes()->count())->toBe($fields)
        ->and(AttributeLayout::query()->where('product_category_id', $this->target->id)->orderBy('id')->pluck('layout')->all())->toBe($forms);
});

it('stops when the ids name other categories', function () {
    $this->target->update(['name' => 'Altra categoria']);

    expect(fn () => $this->seed(QualificaAvvalimentiTenderFieldsSeeder::class))->toThrow(RuntimeException::class);
    expect($this->target->attributes()->count())->toBe(1);
});
