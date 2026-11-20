<?php

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use App\Models\WorkOrderPaymentStatus;
use App\Services\WorkOrders\WorkOrderPaymentStatusAttributeMover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0402: the mover that turned the "Stato pagamento" flexible field into
 * a column of the commessa. The column was dropped by spec 0403 (the status
 * lives on the lines), so the arrange re-adds it the way the fold migration's
 * down() does; the field/PATCH/permission tests are gone (REQUIREMENT CHANGED).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    (include database_path('migrations/2026_12_13_100000_fold_work_order_payment_status_into_lines.php'))->down();
});

/** A legacy-keyed payment status (`old_id` = the flexible field's value). */
function legacyPaymentStatus(int $oldId, string $name): WorkOrderPaymentStatus
{
    $status = WorkOrderPaymentStatus::factory()->create(['name' => $name]);
    $status->forceFill(['old_id' => $oldId])->save();

    return $status;
}

/** @param array<string, mixed>|null $values */
function workOrderWithFlexibleValues(?array $values): WorkOrder
{
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    DB::table('work_orders')->where('id', $workOrder->id)->update(['attribute_values' => $values === null ? null : json_encode($values)]);

    return $workOrder;
}

describe('moving the flexible field to the column', function () {
    it('maps each value through the legacy key and drops the key from the flexible values', function () {
        Attribute::factory()->create(['code' => 'stato_pagamento']);
        $paid = legacyPaymentStatus(3, 'Saldato');
        $start = legacyPaymentStatus(4, 'Iniziare la lavorazione');
        $paidOrder = workOrderWithFlexibleValues(['stato_pagamento' => '3', 'standard' => 'ISO 9001']);
        $startOrder = workOrderWithFlexibleValues(['stato_pagamento' => '4']);
        $emptyOrder = workOrderWithFlexibleValues(['stato_pagamento' => null, 'standard' => 'ISO 14001']);
        $unknownOrder = workOrderWithFlexibleValues(['stato_pagamento' => '99']);
        $untouched = workOrderWithFlexibleValues(['standard' => 'ISO 45001']);

        $result = app(WorkOrderPaymentStatusAttributeMover::class)->handle();

        expect($result)->toMatchArray(['moved' => 2, 'cleared' => 4, 'unmapped' => 1])
            ->and($paidOrder->fresh()->work_order_payment_status_id)->toBe($paid->id)
            ->and($paidOrder->fresh()->attribute_values)->toBe(['standard' => 'ISO 9001'])
            ->and($startOrder->fresh()->work_order_payment_status_id)->toBe($start->id)
            ->and($startOrder->fresh()->attribute_values)->toBeNull()
            ->and($emptyOrder->fresh()->work_order_payment_status_id)->toBeNull()
            ->and($emptyOrder->fresh()->attribute_values)->toBe(['standard' => 'ISO 14001'])
            ->and($unknownOrder->fresh()->work_order_payment_status_id)->toBeNull()
            ->and($untouched->fresh()->attribute_values)->toBe(['standard' => 'ISO 45001']);
    });

    it('keeps a status already set on the column', function () {
        Attribute::factory()->create(['code' => 'stato_pagamento']);
        legacyPaymentStatus(3, 'Saldato');
        $chosen = legacyPaymentStatus(9, 'Insoluto');
        $workOrder = workOrderWithFlexibleValues(['stato_pagamento' => '3']);
        $workOrder->forceFill(['work_order_payment_status_id' => $chosen->id])->saveQuietly();

        app(WorkOrderPaymentStatusAttributeMover::class)->handle();

        expect($workOrder->fresh()->work_order_payment_status_id)->toBe($chosen->id)
            ->and($workOrder->fresh()->attribute_values)->toBeNull();
    });

    it('detaches the field from every category, prunes it from the Commessa layouts and deletes it with its options', function () {
        $attribute = Attribute::factory()->create(['code' => 'stato_pagamento']);
        DB::table('attribute_options')->insert(['attribute_id' => $attribute->id, 'value' => '3', 'label' => 'Saldato', 'sort_order' => 0]);
        $other = Attribute::factory()->create(['code' => 'accordo_pagamento']);
        $category = ProductCategory::factory()->create();
        foreach ([$attribute, $other] as $index => $field) {
            DB::table('attribute_category')->insert([
                'attribute_id' => $field->id,
                'category_id' => $category->id,
                'context' => AttributeContext::WorkOrder->value,
                'is_required' => false,
                'sort_order' => $index,
            ]);
        }
        $layoutId = DB::table('attribute_layouts')->insertGetId([
            'product_category_id' => $category->id,
            'context' => AttributeContext::WorkOrder->value,
            'form_mode' => 'view',
            'layout' => json_encode(['sections' => [
                ['id' => 's1', 'title' => 'Dati contrattuali', 'rows' => [
                    ['id' => 'r1', 'items' => [['width' => 'half', 'attribute_code' => 'stato_pagamento'], ['width' => 'half', 'attribute_code' => 'accordo_pagamento']]],
                ]],
                ['id' => 's2', 'title' => 'Solo stato', 'rows' => [['id' => 'r2', 'items' => [['width' => 'full', 'attribute_code' => 'stato_pagamento']]]]],
            ]]),
        ]);

        $result = app(WorkOrderPaymentStatusAttributeMover::class)->handle();

        $layout = json_decode((string) DB::table('attribute_layouts')->where('id', $layoutId)->value('layout'), true);
        expect($result)->toMatchArray(['detached' => 1, 'layouts' => 1, 'deleted' => 1])
            ->and(DB::table('attribute_category')->where('attribute_id', $attribute->id)->count())->toBe(0)
            ->and(DB::table('attribute_category')->where('attribute_id', $other->id)->count())->toBe(1)
            ->and($layout['sections'])->toHaveCount(1)
            ->and($layout['sections'][0]['rows'][0]['items'])->toBe([['width' => 'half', 'attribute_code' => 'accordo_pagamento']])
            ->and(Attribute::query()->whereKey($attribute->id)->exists())->toBeFalse()
            ->and(DB::table('attribute_options')->where('attribute_id', $attribute->id)->count())->toBe(0)
            ->and(Attribute::query()->whereKey($other->id)->exists())->toBeTrue();
    });

    it('fills a commessa without status from its lines when they all share one status, the flexible value winning', function () {
        Attribute::factory()->create(['code' => 'stato_pagamento']);
        $paid = legacyPaymentStatus(3, 'Saldato');
        $start = legacyPaymentStatus(4, 'Iniziare la lavorazione');
        $linePayment = function (WorkOrder $workOrder, ?WorkOrderPaymentStatus $status): void {
            $line = contractDataLine($workOrder, 'consultancy', 100);
            WorkOrderLinePayment::query()->create(['work_order_id' => $workOrder->id, 'quote_line_id' => $line->id, 'work_order_payment_status_id' => $status?->id]);
        };
        $uniform = workOrderWithFlexibleValues(null);
        $linePayment($uniform, $paid);
        $linePayment($uniform, $paid);
        $mixed = workOrderWithFlexibleValues(null);
        $linePayment($mixed, $paid);
        $linePayment($mixed, $start);
        $partial = workOrderWithFlexibleValues(null);
        $linePayment($partial, $paid);
        $linePayment($partial, null);
        $flexible = workOrderWithFlexibleValues(['stato_pagamento' => '4']);
        $linePayment($flexible, $paid);

        $result = app(WorkOrderPaymentStatusAttributeMover::class)->handle();

        expect($result['from_lines'])->toBe(1)
            ->and($uniform->fresh()->work_order_payment_status_id)->toBe($paid->id)
            ->and($mixed->fresh()->work_order_payment_status_id)->toBeNull()
            ->and($partial->fresh()->work_order_payment_status_id)->toBeNull()
            ->and($flexible->fresh()->work_order_payment_status_id)->toBe($start->id);
    });

    it('changes nothing on a second run nor without the attribute', function () {
        expect(app(WorkOrderPaymentStatusAttributeMover::class)->handle())->toBe(['moved' => 0, 'cleared' => 0, 'unmapped' => 0, 'from_lines' => 0, 'detached' => 0, 'layouts' => 0, 'deleted' => 0]);

        Attribute::factory()->create(['code' => 'stato_pagamento']);
        $paid = legacyPaymentStatus(3, 'Saldato');
        $workOrder = workOrderWithFlexibleValues(['stato_pagamento' => '3']);
        app(WorkOrderPaymentStatusAttributeMover::class)->handle();

        expect(app(WorkOrderPaymentStatusAttributeMover::class)->handle())->toMatchArray(['moved' => 0, 'cleared' => 0])
            ->and($workOrder->fresh()->work_order_payment_status_id)->toBe($paid->id);
    });
});
