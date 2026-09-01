<?php

namespace App\Models;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CommissionConfigurationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'name', 'recipient_role', 'application_scope', 'product_category_id',
    'product_id', 'recipient_type', 'recipient_id', 'commission_type', 'value',
    'priority', 'valid_from', 'valid_until', 'status', 'internal_note',
])]
class CommissionConfiguration extends BaseModel
{
    /** @use HasFactory<CommissionConfigurationFactory> */
    use HasFactory, LogsModelActivity;

    protected function casts(): array
    {
        return [
            'recipient_role' => CommissionRecipientRole::class,
            'application_scope' => CommissionApplicationScope::class,
            'commission_type' => CommissionType::class,
            'value' => 'decimal:4',
            'priority' => 'integer',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'status' => CommissionConfigurationStatus::class,
        ];
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The single recipient this rule is scoped to (spec 0089 D-1), or no
     * relation loaded when the rule is a plain role-wide default.
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }

    public function appliedCommissions(): HasMany
    {
        return $this->hasMany(QuoteLineCommission::class);
    }
}
