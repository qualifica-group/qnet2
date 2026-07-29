<?php

namespace App\Models;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\QuoteLineCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'quote_line_id', 'commission_configuration_id', 'recipient_role',
    'recipient_type', 'recipient_id', 'commission_type', 'value',
    'calculated_amount', 'internal_note', 'origin',
])]
class QuoteLineCommission extends BaseModel
{
    /** @use HasFactory<QuoteLineCommissionFactory> */
    use HasFactory, LogsModelActivity;

    protected function casts(): array
    {
        return [
            'recipient_role' => CommissionRecipientRole::class,
            'commission_type' => CommissionType::class,
            'value' => 'decimal:4',
            'calculated_amount' => 'decimal:2',
            'origin' => CommissionOrigin::class,
        ];
    }

    public function quoteLine(): BelongsTo
    {
        return $this->belongsTo(QuoteLine::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(CommissionConfiguration::class, 'commission_configuration_id');
    }

    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
