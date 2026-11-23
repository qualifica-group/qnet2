<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Database\Seeders\RemoveRetiredWorkOrderAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/*
 * One-shot (user request 2026-11-16): the commessa flexible fields "Accordo
 * sui Pagamenti", "Insoluti", "Comunicazioni Email Commerciale", "Proforma
 * emessa" and "Totale commessa manuale" leave the values, the categories, the
 * layouts and the catalogue; every other field stays.
 */

uses(RefreshDatabase::class);

function retiredFieldsLayout(ProductCategory $category): int
{
    return DB::table('attribute_layouts')->insertGetId([
        'product_category_id' => $category->id,
        'context' => AttributeContext::WorkOrder->value,
        'form_mode' => 'view',
        'layout' => json_encode(['sections' => [
            ['id' => 's1', 'title' => 'Dati contrattuali', 'rows' => [
                ['id' => 'r1', 'items' => [['width' => 'half', 'attribute_code' => 'insoluti'], ['width' => 'half', 'attribute_code' => 'comunicazione_email']]],
                ['id' => 'r2', 'items' => [['width' => 'half', 'attribute_code' => 'status_contabile'], ['width' => 'half', 'attribute_code' => 'standard']]],
            ]],
        ]]),
    ]);
}

it('deletes the five fields with their values, assignments, options and layout places, keeping the others', function () {
    $retired = collect(RemoveRetiredWorkOrderAttributesSeeder::ATTRIBUTE_CODES)
        ->map(fn (string $code): Attribute => Attribute::factory()->create(['code' => $code]));
    $kept = Attribute::factory()->create(['code' => 'standard']);
    DB::table('attribute_options')->insert(['attribute_id' => $retired[2]->id, 'value' => '1', 'label' => 'CC', 'sort_order' => 0]);
    $category = ProductCategory::factory()->create();
    foreach ([...$retired, $kept] as $index => $field) {
        DB::table('attribute_category')->insert([
            'attribute_id' => $field->id, 'category_id' => $category->id,
            'context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => $index,
        ]);
    }
    $layoutId = retiredFieldsLayout($category);
    $mixed = WorkOrder::factory()->create(['attribute_values' => ['status_contabile' => true, 'insoluti' => false, 'standard' => ['9']]]);
    $retiredOnly = WorkOrder::factory()->create(['attribute_values' => ['totale_commessa_manuale' => '1200.00']]);
    $untouched = WorkOrder::factory()->create(['attribute_values' => ['standard' => ['1']]]);

    $this->seed(RemoveRetiredWorkOrderAttributesSeeder::class);

    $layout = json_decode((string) DB::table('attribute_layouts')->where('id', $layoutId)->value('layout'), true);
    expect(Attribute::query()->whereIn('code', RemoveRetiredWorkOrderAttributesSeeder::ATTRIBUTE_CODES)->count())->toBe(0)
        ->and(Attribute::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(DB::table('attribute_options')->count())->toBe(0)
        ->and(DB::table('attribute_category')->pluck('attribute_id')->all())->toBe([$kept->id])
        ->and($layout['sections'][0]['rows'])->toHaveCount(1)
        ->and($layout['sections'][0]['rows'][0]['items'])->toBe([['width' => 'half', 'attribute_code' => 'standard']])
        ->and($mixed->fresh()->attribute_values)->toBe(['standard' => ['9']])
        ->and($retiredOnly->fresh()->attribute_values)->toBeNull()
        ->and($untouched->fresh()->attribute_values)->toBe(['standard' => ['1']]);
});

it('changes nothing when run again', function () {
    Attribute::factory()->create(['code' => 'insoluti']);
    $workOrder = WorkOrder::factory()->create(['attribute_values' => ['insoluti' => true]]);

    $this->seed(RemoveRetiredWorkOrderAttributesSeeder::class);
    $this->seed(RemoveRetiredWorkOrderAttributesSeeder::class);

    expect(Attribute::query()->where('code', 'insoluti')->exists())->toBeFalse()
        ->and($workOrder->fresh()->attribute_values)->toBeNull();
});
