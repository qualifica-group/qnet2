<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "funzione aziendale" + "categoria prodotto" row of a commessa born
 * without an offer (spec 0431, D-7), mirroring ProjectProductLine. A commessa
 * born from an offer never has rows here: its functions come from the offer.
 * No activity log on this row (child collection of the commessa).
 */
#[Fillable(['work_order_id', 'business_function_id', 'product_category_id'])]
class WorkOrderProductLine extends BaseModel
{
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function businessFunction(): BelongsTo
    {
        return $this->belongsTo(BusinessFunction::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
