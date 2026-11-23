<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Integrations\Formalab\Models\FormalabCategorySetting;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\ClassCatalogSync;
use Integrations\Formalab\Services\LinkPropagator;
use Integrations\Formalab\Support\Trigger;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const FORMALAB_CATEGORY_URL = '/api/integrations/formalab/product-categories/';

/**
 * A category carrying the given work order attributes (code => type).
 *
 * @param  array<string, string>  $attributes
 */
function formalabCategoryWith(array $attributes, ?ProductCategory $parent = null): ProductCategory
{
    $category = ProductCategory::factory()->create(['parent_id' => $parent?->id]);
    $position = 0;

    foreach ($attributes as $code => $type) {
        $attribute = Attribute::query()->where('code', $code)->first() ?? Attribute::factory()->create(['code' => $code, 'type' => $type]);
        $category->attributes()->attach($attribute->id, ['is_required' => false, 'sort_order' => $position++, 'context' => AttributeContext::WorkOrder->value]);
    }

    return $category;
}

/** @return list<array{formalab_field: string, attribute_code: string}> */
function formalabPairs(array $map): array
{
    return collect($map)->map(fn (string $code, string $field): array => ['formalab_field' => $field, 'attribute_code' => $code])->values()->all();
}

// AC-001
it('describes a never configured root: not linked, no map, the 18 fields and the category attributes', function () {
    formalabEnable();
    $category = formalabCategoryWith(['gol_corso' => 'text', 'gol_data_esame' => 'date', 'note_interne' => 'textarea']);
    Sanctum::actingAs(productCategoryUserWith(['view']));

    $response = $this->getJson(FORMALAB_CATEGORY_URL.$category->id)->assertOk();

    $response->assertJsonPath('data.is_root', true)->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.enabled_source_category', null)->assertJsonPath('data.own_field_map', false)
        ->assertJsonPath('data.field_map', [])->assertJsonPath('data.can_manage', false)
        ->assertJsonCount(18, 'data.fields')->assertJsonCount(3, 'data.attributes');
    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['label']['is_key'])->toBeTrue()
        ->and($fields['label']['suggested_attribute_code'])->toBe('gol_corso')
        ->and($fields['exam_date']['suggested_attribute_code'])->toBe('gol_data_esame')
        ->and($fields['exam_date']['compatible_types'])->toBe(['date', 'text', 'textarea'])
        ->and($fields['start_date']['suggested_attribute_code'])->toBeNull();
});

// AC-002
it('lets the root decide the link and the children inherit it, with a map inherited from the nearest ancestor', function () {
    formalabEnable();
    $root = formalabCategoryWith(['gol_corso' => 'text']);
    $child = formalabCategoryWith(['altro_nome' => 'text'], $root);
    $grandchild = formalabCategoryWith([], $child);
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->putJson(FORMALAB_CATEGORY_URL.$root->id, ['enabled' => true, 'field_map' => formalabPairs(['label' => 'gol_corso'])])
        ->assertOk()->assertJsonPath('data.enabled', true)->assertJsonPath('data.own_field_map', true)->assertJsonPath('data.can_manage', true);

    $this->getJson(FORMALAB_CATEGORY_URL.$grandchild->id)->assertOk()
        ->assertJsonPath('data.is_root', false)->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.enabled_source_category.id', $root->id)
        ->assertJsonPath('data.own_field_map', false)->assertJsonPath('data.field_map_source_category.id', $root->id)
        ->assertJsonPath('data.field_map', formalabPairs(['label' => 'gol_corso']));

    // A child may declare its own map: its descendants inherit it, then it goes back to inheriting
    $this->putJson(FORMALAB_CATEGORY_URL.$child->id, ['field_map' => formalabPairs(['label' => 'altro_nome'])])->assertOk()
        ->assertJsonPath('data.own_field_map', true)->assertJsonPath('data.field_map_source_category', null);
    $this->getJson(FORMALAB_CATEGORY_URL.$grandchild->id)->assertJsonPath('data.field_map_source_category.id', $child->id)
        ->assertJsonPath('data.field_map', formalabPairs(['label' => 'altro_nome']));
    $this->putJson(FORMALAB_CATEGORY_URL.$child->id, ['field_map' => null])->assertOk()
        ->assertJsonPath('data.own_field_map', false)->assertJsonPath('data.field_map', formalabPairs(['label' => 'gol_corso']));

    // The child cannot switch the link off on its own; repeating the inherited value is harmless
    $this->putJson(FORMALAB_CATEGORY_URL.$child->id, ['enabled' => false, 'field_map' => null])->assertUnprocessable()->assertJsonValidationErrors('enabled');
    $this->putJson(FORMALAB_CATEGORY_URL.$child->id, ['enabled' => true, 'field_map' => null])->assertOk();

    // Switching the root off switches the whole branch off
    $this->putJson(FORMALAB_CATEGORY_URL.$root->id, ['enabled' => false, 'field_map' => formalabPairs(['label' => 'gol_corso'])])->assertOk();
    $this->getJson(FORMALAB_CATEGORY_URL.$grandchild->id)->assertJsonPath('data.enabled', false);
});

// AC-003
it('refuses a map the category cannot hold', function (array $map, string $errorKey) {
    formalabEnable();
    $category = formalabCategoryWith(['gol_corso' => 'text', 'gol_data_esame' => 'date', 'gol_ore' => 'integer']);
    Attribute::factory()->create(['code' => 'other_category_field', 'type' => 'text']);
    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));

    $this->putJson(FORMALAB_CATEGORY_URL.$category->id, ['enabled' => true, 'field_map' => $map])
        ->assertUnprocessable()->assertJsonValidationErrors($errorKey);
    expect(FormalabCategorySetting::query()->count())->toBe(0);
})->with([
    'unknown field' => [[['formalab_field' => 'nope', 'attribute_code' => 'gol_corso']], 'field_map.0.formalab_field'],
    'repeated field' => [[['formalab_field' => 'label', 'attribute_code' => 'gol_corso'], ['formalab_field' => 'label', 'attribute_code' => 'gol_data_esame']], 'field_map.1.formalab_field'],
    'repeated attribute' => [[['formalab_field' => 'label', 'attribute_code' => 'gol_corso'], ['formalab_field' => 'title', 'attribute_code' => 'gol_corso']], 'field_map.1.attribute_code'],
    'attribute of another category' => [[['formalab_field' => 'label', 'attribute_code' => 'other_category_field']], 'field_map.0.attribute_code'],
    'incompatible type' => [[['formalab_field' => 'label', 'attribute_code' => 'gol_corso'], ['formalab_field' => 'course_status', 'attribute_code' => 'gol_data_esame']], 'field_map.1.attribute_code'],
    'number on a date' => [[['formalab_field' => 'label', 'attribute_code' => 'gol_corso'], ['formalab_field' => 'total_hours', 'attribute_code' => 'gol_data_esame']], 'field_map.1.attribute_code'],
    'no course name' => [[['formalab_field' => 'exam_date', 'attribute_code' => 'gol_data_esame']], 'field_map'],
]);

it('authorizes with the category policy', function () {
    formalabEnable();
    $category = formalabCategoryWith(['gol_corso' => 'text']);
    $payload = ['enabled' => true, 'field_map' => formalabPairs(['label' => 'gol_corso'])];

    Sanctum::actingAs(productCategoryUserWith(['view']));
    $this->putJson(FORMALAB_CATEGORY_URL.$category->id, $payload)->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson(FORMALAB_CATEGORY_URL.$category->id)->assertForbidden();

    Sanctum::actingAs(productCategoryUserWith(['view', 'update']));
    $this->getJson(FORMALAB_CATEGORY_URL.'999999')->assertNotFound();
    expect(FormalabCategorySetting::query()->count())->toBe(0);
});

// AC-004
it('writes only the mapped fields, into the attributes the category chose', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(5, ['data_esame' => '2026-07-10'])]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5', 'gol_notice' => 'keep me'], linkCategory: false);
    $category = $workOrder->quoteLines->first()->product->category;
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    // Not linked: not eligible even though the course fields exist
    $this->getJson('/api/integrations/formalab/work-orders/'.$workOrder->id)->assertOk()->assertJsonPath('data.eligible', false);
    $this->postJson('/api/integrations/formalab/work-orders/'.$workOrder->id.'/sync')->assertUnprocessable();

    // Linked with a map using a non-gol attribute for the exam date
    formalabLinkCategory($category, formalabPairs(['label' => 'gol_corso', 'exam_date' => 'gol_notice']));
    $this->postJson('/api/integrations/formalab/work-orders/'.$workOrder->id.'/sync')->assertOk()->assertJsonPath('data.eligible', true);

    $values = $workOrder->refresh()->attribute_values;
    expect($values['gol_notice'])->toBe('2026-07-10')
        ->and($values['gol_data_esame'] ?? null)->toBeNull()
        ->and($values['gol_stato_corso'] ?? null)->toBeNull();
});

// AC-005
it('records a sync error and writes nothing when the category was unlinked after the link', function () {
    formalabEnable();
    $catalog = formalabFakeApi([formalabClassPayload(5)]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $workOrder = formalabGolWorkOrder(['gol_corso' => 'COD-5 - Course 5']);
    FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => 5]);
    app(LinkPropagator::class)->propagate();
    $category = $workOrder->quoteLines->first()->product->category;
    formalabLinkCategory($category, formalabSuggestedMap(), enabled: false);
    $catalog['classes'] = [formalabClassPayload(5, ['stato_corso' => ['name' => 'Concluso']])];
    app(ClassCatalogSync::class)->handle(Trigger::Manual);

    $link = FormalabWorkOrderLink::query()->first();
    expect($link->sync_error)->not->toBeNull()
        ->and($workOrder->refresh()->attribute_values['gol_stato_corso'])->toBe('In corso');
});

// AC-006
it('backfills only the work orders of a linked category, by the attribute mapped on the course name', function () {
    formalabEnable();
    formalabFakeApi([formalabClassPayload(1, ['codice' => 'AAA', 'corso' => ['title' => 'Unique']])]);
    app(ClassCatalogSync::class)->handle(Trigger::Manual);
    $linked = formalabGolWorkOrder(['gol_corso' => 'AAA - Unique']);
    $notLinked = formalabGolWorkOrder(['gol_corso' => 'AAA - Unique'], linkCategory: false);
    Sanctum::actingAs(formalabSuperAdmin());

    $this->postJson('/api/integrations/formalab/link-work-orders')->assertOk()
        ->assertJsonPath('data', ['linked' => 1, 'ambiguous' => 0, 'unmatched' => 0]);

    expect(FormalabWorkOrderLink::query()->pluck('work_order_id')->all())->toBe([$linked->id])
        ->and(FormalabWorkOrderLink::query()->where('work_order_id', $notLinked->id)->exists())->toBeFalse()
        ->and(FormalabClass::query()->count())->toBe(1);
});
