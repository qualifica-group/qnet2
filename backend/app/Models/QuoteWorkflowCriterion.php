<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use Database\Factories\QuoteWorkflowCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One matching criterion of a QuoteWorkflow (spec 0047; moved onto the
 * Offerta by spec 0083, D-6/D-7): `field` is an allow-list key
 * (App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry), `value_id` the
 * chosen value's id. A workflow matches a Quote only when EVERY one of its
 * criteria matches (AND, AC-013). No activity log on this row (pure child
 * collection of the workflow, which already logs its own changes, mirroring
 * OpportunityProductLine).
 */
#[Fillable(['quote_workflow_id', 'field', 'value_id'])]
class QuoteWorkflowCriterion extends BaseModel
{
    /** @use HasFactory<QuoteWorkflowCriterionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_id' => 'int',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(QuoteWorkflow::class, 'quote_workflow_id');
    }
}
