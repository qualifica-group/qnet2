<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteWorkflowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quote workflow configurator (spec 0047; moved onto the Offerta by spec
 * 0083, D-6): a named, activatable set of matching criteria (`criteria()`)
 * plus its own working-state statuses (`statuses()`) applied to a Quote.
 * `criteria_signature` is the deterministic "field:value_id|..." string that
 * enforces criteria-combination uniqueness (AC-009) — computed/written only
 * by the service that syncs a workflow's criteria, DELIBERATELY absent from
 * #[Fillable].
 */
#[Fillable(['name', 'is_active'])]
class QuoteWorkflow extends BaseModel
{
    /** @use HasFactory<QuoteWorkflowFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<QuoteWorkflowCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(QuoteWorkflowCriterion::class);
    }

    /**
     * @return HasMany<QuoteWorkflowStatus, $this>
     */
    public function statuses(): HasMany
    {
        return $this->hasMany(QuoteWorkflowStatus::class);
    }
}
