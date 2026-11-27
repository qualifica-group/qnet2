<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use Database\Seeders\QualificaCatalog\GdprCatalogue;
use Database\Seeders\QualificaCatalog\GdprWorkOrderCatalogue;
use Database\Seeders\QualificaGdprWorkOrderFieldsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The current "GDPR" root takes the [OLD] GDPR Commessa set as fields of its
 * own, with their form, and the legacy fields are linked to the new ones.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->root = ProductCategory::factory()->create(['name' => GdprCatalogue::ROOT, 'parent_id' => null]);
    $this->legacy = ProductCategory::factory()->create(['name' => '[OLD] DPO']);

    foreach (GdprWorkOrderCatalogue::LINKS as $legacyCode => $currentCode) {
        $type = collect(GdprWorkOrderCatalogue::ATTRIBUTES)->firstWhere('code', $currentCode)['type'];
        categoryTabAttribute(AttributeContext::WorkOrder, $type === 'decimal' ? 'integer' : $type, ['code' => $legacyCode], $this->legacy);
    }

    categoryTabAttribute(AttributeContext::WorkOrder, 'enum', ['code' => 'gdpr_intervista'], $this->legacy);
});

function gdprWorkOrderCodes(ProductCategory $category): array
{
    return $category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->pluck('code')->sort()->values()->all();
}

it('assigns the Commessa fields and form to the root, the legacy category keeping its own', function () {
    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);

    $layout = AttributeLayout::query()->where('product_category_id', $this->root->id)->where('context', AttributeContext::WorkOrder->value)->sole();
    $formCodes = collect($layout->layout['sections'])->flatMap(fn (array $section) => collect($section['rows'])->flatMap(fn (array $row) => array_column($row['items'], 'attribute_code')))->sort()->values()->all();
    $expected = collect(GdprWorkOrderCatalogue::ATTRIBUTES)->pluck('code')->sort()->values()->all();

    expect(gdprWorkOrderCodes($this->root))->toBe($expected)
        ->and($formCodes)->toBe($expected)
        ->and(array_column($layout->layout['sections'], 'id'))->toBe(array_column(GdprWorkOrderCatalogue::SECTIONS, 0))
        ->and(Attribute::query()->where('code', 'gdpr_interview_location')->sole()->options()->pluck('value')->all())->toBe(['on_site', 'off_site'])
        ->and(gdprWorkOrderCodes($this->legacy))->toContain('gdpr_accessi_onsite', 'gdpr_intervista');
});

it('links each legacy field to its new field, the enums left apart', function () {
    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);

    $ids = Attribute::query()->pluck('id', 'code');

    foreach (GdprWorkOrderCatalogue::LINKS as $legacyCode => $currentCode) {
        expect(Attribute::query()->where('code', $legacyCode)->value('current_attribute_id'))->toBe($ids[$currentCode]);
    }

    expect(Attribute::query()->where('code', 'gdpr_intervista')->value('current_attribute_id'))->toBeNull();
});

it('links the compatible [OLD] GDPR offer fields to the current ones, the checklist enum left apart', function () {
    $employees = Attribute::factory()->ofType('integer')->create(['code' => 'gdpr_employee_count']);
    $activity = Attribute::factory()->ofType('textarea')->create(['code' => 'gdpr_business_activity']);
    Attribute::factory()->ofType('boolean')->create(['code' => 'gdpr_checklist']);
    Attribute::factory()->ofType('integer')->create(['code' => 'numero_dipendenti']);
    Attribute::factory()->ofType('text')->create(['code' => 'attivita_prevalente']);
    Attribute::factory()->enum(2)->create(['code' => 'check_list']);

    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);

    expect(array_keys(GdprCatalogue::QUOTE_LINKS))->toBe(['numero_dipendenti', 'attivita_prevalente'])
        ->and(Attribute::query()->where('code', 'numero_dipendenti')->value('current_attribute_id'))->toBe($employees->id)
        ->and(Attribute::query()->where('code', 'attivita_prevalente')->value('current_attribute_id'))->toBe($activity->id)
        ->and(Attribute::query()->where('code', 'check_list')->value('current_attribute_id'))->toBeNull();
});

it('keeps a form configured by hand and an existing link', function () {
    $other = Attribute::factory()->ofType('date')->create();
    Attribute::query()->where('code', 'gdpr_richiamo')->update(['current_attribute_id' => $other->id]);
    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);
    $layout = AttributeLayout::query()->where('product_category_id', $this->root->id)->where('context', AttributeContext::WorkOrder->value)->sole();
    $layout->update(['layout' => ['sections' => []]]);

    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);

    expect($layout->fresh()->layout)->toBe(['sections' => []])
        ->and(AttributeLayout::query()->where('product_category_id', $this->root->id)->where('form_mode', LayoutFormScope::All->value)->count())->toBe(1)
        ->and(Attribute::query()->where('code', 'gdpr_richiamo')->value('current_attribute_id'))->toBe($other->id)
        ->and($this->root->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->count())->toBe(count(GdprWorkOrderCatalogue::ATTRIBUTES));
});

it('fails loudly without the GDPR root', function () {
    $this->root->delete();

    $this->seed(QualificaGdprWorkOrderFieldsSeeder::class);
})->throws(ModelNotFoundException::class);
