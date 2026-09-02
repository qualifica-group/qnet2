<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\ProjectProductLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One "funzione aziendale" + "categoria prodotto" row against a Project
 * (spec 0094, mirroring OpportunityProductLine, spec 0040 amendment rev.3):
 * SUBSTITUTES the former single `business_function_id`/`product_category_id`
 * columns on `projects` with a one-to-many collection — the exact pair is
 * unique (`project_id`, `business_function_id`, `product_category_id`). No
 * activity log on this row (pure child collection of the Project, which
 * already logs its own changes).
 */
#[Fillable(['project_id', 'business_function_id', 'product_category_id'])]
class ProjectProductLine extends BaseModel
{
    /** @use HasFactory<ProjectProductLineFactory> */
    use HasFactory;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
