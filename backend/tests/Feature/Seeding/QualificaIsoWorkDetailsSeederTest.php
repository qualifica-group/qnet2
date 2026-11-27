<?php

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Database\Seeders\QualificaIsoWorkDetailsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Commessa of ISO and [OLD] ISO: "Standard ISO" leaves it, a new "Scopo"
 * follows the urgency date, and the ISO form takes the sections, fields and
 * order of [OLD] ISO; values and other categories stay.
 */
uses(RefreshDatabase::class);

function isoDetailsRows(string $sectionId, array $rows): array
{
    return [
        'id' => $sectionId, 'title' => $sectionId, 'sort_order' => 0, 'variant' => 'default', 'description' => null,
        'collapsible' => false, 'default_collapsed' => false, 'visible_when' => null, 'columns' => 2,
        'rows' => array_map(static fn (array $codes, int $index): array => [
            'id' => "{$sectionId}-{$index}",
            'items' => array_map(static fn (string $code): array => ['width' => 'half', 'attribute_code' => $code], $codes),
        ], $rows, array_keys($rows)),
    ];
}

function isoDetailsForm(ProductCategory $category, array $sections): AttributeLayout
{
    return AttributeLayout::query()->create([
        'product_category_id' => $category->id,
        'context' => AttributeContext::WorkOrder,
        'form_mode' => LayoutFormScope::All,
        'layout' => ['sections' => $sections, 'canonical_order' => true],
    ]);
}

function isoDetailsCodes(AttributeLayout $form): array
{
    return array_map(
        static fn (array $section): array => array_map(static fn (array $row): array => array_column($row['items'], 'attribute_code'), $section['rows']),
        $form->fresh()->layout['sections'],
    );
}

function isoDetailsAssigned(ProductCategory $category): array
{
    return $category->attributes()->wherePivot('context', AttributeContext::WorkOrder->value)->pluck('code')->sort()->values()->all();
}

beforeEach(function (): void {
    $this->iso = ProductCategory::factory()->create(['name' => 'ISO', 'parent_id' => null]);
    $this->old = ProductCategory::factory()->create(['name' => QualificaIsoWorkDetailsSeeder::OLD_ISO_ROOT, 'parent_id' => null]);
    $this->oldChild = ProductCategory::factory()->create(['name' => '[OLD] ISO_9001', 'parent_id' => $this->old->id]);
    $this->other = ProductCategory::factory()->create(['name' => '[OLD] PROGETTI-', 'parent_id' => null]);

    $this->isoChild = ProductCategory::factory()->create(['name' => 'ISO - Ente', 'parent_id' => $this->iso->id]);

    foreach ([['code' => 'iso_standards', 'type' => 'enum'], ...IsoWorkOrderCatalogue::ATTRIBUTES] as $spec) {
        if ($spec['code'] !== QualificaIsoWorkDetailsSeeder::SCOPE_CODE) {
            categoryTabAttribute(AttributeContext::WorkOrder, $spec['type'], ['code' => $spec['code'], 'name' => $spec['code']], $this->iso);
        }
    }

    foreach (['standard' => 'enum', 'urgenza_emissione' => 'text', 'data_urgenza_emissione' => 'date', 'scopo' => 'textarea'] as $code => $type) {
        [, $attribute] = categoryTabAttribute(AttributeContext::WorkOrder, $type, ['code' => $code], $this->old);
        $this->oldChild->attributes()->attach($attribute->id, ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 4]);
    }

    $standard = Attribute::query()->where('code', 'standard')->sole();
    $this->other->attributes()->attach($standard->id, ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 0]);
    $this->old->attributes()->attach($standard->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);

    $this->isoForm = isoDetailsForm($this->iso, [isoDetailsRows('iso-work-details', [
        ['iso_standards'], ['iso_certificates'], ['iso_scope'], ['iso_urgent_issue_date', 'iso_issue_urgency'],
    ])]);
    $oldSections = [
        isoDetailsRows('iso-dettagli-lavorazioni', [['standard'], ['urgenza_emissione', 'data_urgenza_emissione']]),
        isoDetailsRows('iso-dettagli-lavorazioni-iso', [['scopo']]),
    ];
    $this->oldForm = isoDetailsForm($this->old, $oldSections);
    $this->oldChildForm = isoDetailsForm($this->oldChild, $oldSections);
    $this->otherForm = isoDetailsForm($this->other, [isoDetailsRows('iso-dettagli-lavorazioni', [['standard'], ['data_urgenza_emissione']])]);
});

it('gives ISO the sections, fields and order of [OLD] ISO, without "Standard ISO"', function () {
    $this->seed(QualificaIsoWorkDetailsSeeder::class);

    $sections = $this->isoForm->fresh()->layout['sections'];
    $assigned = isoDetailsAssigned($this->iso);

    expect(array_column($sections, 'title'))->toBe(['Dettagli lavorazioni', 'Focus lavorazione ISO', 'Verifiche ispettive', 'Dettagli lavorazioni ISO'])
        ->and(isoDetailsCodes($this->isoForm))->toBe([
            [['iso_certificates'], ['iso_last_audit_date', 'iso_certificate_year'], ['iso_issue_urgency', 'iso_urgent_issue_date'], ['iso_visit_scope']],
            [['iso_interview_location', 'iso_audit_support_location'], ['iso_work_hours', 'iso_assignment_date'], ['iso_work_end_date', 'iso_reference_body'], ['iso_interview_hours', 'iso_audit_support_hours'], ['iso_quotation']],
            [['iso_inspections']],
            [['iso_scope'], ['iso_scope_confirmed'], ['iso_outgoing_scope'], ['iso_consultant_notes'], ['iso_official_audit_date']],
        ])
        ->and($assigned)->toContain('iso_visit_scope')->not->toContain('iso_standards')
        ->and(count($assigned))->toBe(count(IsoWorkOrderCatalogue::ATTRIBUTES))
        ->and(Attribute::query()->whereIn('code', ['iso_scope', 'iso_interview_location'])->orderBy('code')->pluck('name')->all())->toBe(['Intervista in sede', 'Scopo proposto'])
        ->and(AttributeLayout::query()->where('product_category_id', $this->isoChild->id)->exists())->toBeFalse();
});

it('drops "Standard ISO" from [OLD] ISO and its subcategories and adds the new "Scopo" after the urgency date', function () {
    $this->seed(QualificaIsoWorkDetailsSeeder::class);

    $scope = Attribute::query()->where('code', QualificaIsoWorkDetailsSeeder::SCOPE_CODE)->sole();

    foreach ([$this->oldForm, $this->oldChildForm] as $form) {
        expect(isoDetailsCodes($form))->toBe([[['urgenza_emissione', 'data_urgenza_emissione'], ['iso_visit_scope']], [['scopo']]]);
    }

    expect($scope->only(['name', 'type']))->toBe(['name' => 'Scopo', 'type' => 'textarea'])
        ->and(isoDetailsAssigned($this->old))->toBe(['data_urgenza_emissione', 'iso_visit_scope', 'scopo', 'urgenza_emissione'])
        ->and(isoDetailsAssigned($this->oldChild))->toBe(['data_urgenza_emissione', 'iso_visit_scope', 'scopo', 'urgenza_emissione'])
        ->and($this->oldChild->attributes()->where('code', 'iso_visit_scope')->sole()->pivot->sort_order)->toBe(4)
        ->and($this->old->attributes()->wherePivot('context', AttributeContext::Quote->value)->pluck('code')->all())->toBe(['standard']);
});

it('leaves other categories and the stored values alone, and changes nothing on a second run', function () {
    $workOrder = WorkOrder::factory()->create(['attribute_values' => ['standard' => ['1', '4'], 'iso_scope' => 'Progettazione']]);

    $this->seed(QualificaIsoWorkDetailsSeeder::class);
    $afterFirst = DB::table('attribute_layouts')->orderBy('id')->pluck('layout')->all();
    $assignments = DB::table('attribute_category')->count();
    $this->seed(QualificaIsoWorkDetailsSeeder::class);

    expect(isoDetailsAssigned($this->other))->toBe(['standard'])
        ->and(isoDetailsCodes($this->otherForm))->toBe([[['standard'], ['data_urgenza_emissione']]])
        ->and($workOrder->fresh()->attribute_values)->toBe(['standard' => ['1', '4'], 'iso_scope' => 'Progettazione'])
        ->and(DB::table('attribute_layouts')->orderBy('id')->pluck('layout')->all())->toBe($afterFirst)
        ->and(DB::table('attribute_category')->count())->toBe($assignments);
});

it('leaves an [OLD] ISO form without the urgency date as it is', function () {
    $this->oldChildForm->update(['layout' => ['sections' => [isoDetailsRows('iso-dettagli-lavorazioni-iso', [['scopo']])]]]);

    $this->seed(QualificaIsoWorkDetailsSeeder::class);

    expect(isoDetailsCodes($this->oldChildForm))->toBe([[['scopo']]]);
});

it('fails loudly without the [OLD] ISO root', function () {
    $this->old->update(['name' => 'ISO OLD']);

    $this->seed(QualificaIsoWorkDetailsSeeder::class);
})->throws(ModelNotFoundException::class);
