<?php

use App\Enums\AttributeContext;
use App\Enums\WorkOrderType;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;

if (! function_exists('syncedAttributeCategory')) {
    /**
     * A category with one text field `$code` in the Offerta AND in the
     * Commessa, the Commessa assignment synced with the offer when $synced
     * (spec 0425), plus a text field in each context alone.
     */
    function syncedAttributeCategory(string $code = 'sync_registry', bool $synced = true): ProductCategory
    {
        $category = ProductCategory::factory()->create();
        $shared = Attribute::factory()->ofType('text')->create(['code' => $code]);
        $category->attributes()->attach($shared->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
        $category->attributes()->attach($shared->id, ['context' => AttributeContext::WorkOrder->value, 'is_required' => false, 'sort_order' => 0, 'syncs_with_quote' => $synced]);
        categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => $code.'_quote_only'], $category);
        categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => $code.'_work_order_only'], $category);

        return $category;
    }
}

if (! function_exists('syncedAttributeWorkOrder')) {
    /**
     * A mono-offer work order holding the offer's single line.
     */
    function syncedAttributeWorkOrder(Quote $quote, int $renewal = 0): WorkOrder
    {
        $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id, 'type' => WorkOrderType::Processing]);
        $workOrder->quoteLines()->attach($quote->offerLines()->sole()->id, ['renewal_number' => $renewal]);

        return $workOrder;
    }
}
