<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\CampaignProductLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "funzione aziendale" + "categoria prodotto" row against a STANDALONE
 * Campaign (spec 0094, mirroring OpportunityProductLine, spec 0040
 * amendment rev.3): SUBSTITUTES the former single
 * `business_function_id`/`product_category_id` columns on `campaigns` with a
 * one-to-many collection — the exact pair is unique (`campaign_id`,
 * `business_function_id`, `product_category_id`). A campaign LINKED to a
 * project (BR-2) never owns rows here: it keeps reading the project's own
 * collection. No activity log on this row (pure child collection of the
 * Campaign, which already logs its own changes).
 */
#[Fillable(['campaign_id', 'business_function_id', 'product_category_id'])]
class CampaignProductLine extends BaseModel
{
    /** @use HasFactory<CampaignProductLineFactory> */
    use HasFactory;

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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
