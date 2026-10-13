<?php

namespace App\Models;

use App\Enums\EnasarcoMandateType;
use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AgentFiscalProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dated fiscal profile of an agent referent (spec 0212): withholding and
 * ENASARCO settings valid in [valid_from, valid_until] (null = open-ended).
 * Validity ranges of the same referent never overlap (AgentFiscalProfileService).
 */
#[Fillable([
    'referent_id', 'withholding_enabled', 'withholding_rate', 'withholding_base_percent',
    'enasarco_enabled', 'enasarco_mandate_type', 'vat_rate_id', 'valid_from', 'valid_until', 'notes', 'created_by',
])]
class AgentFiscalProfile extends BaseModel
{
    /** @use HasFactory<AgentFiscalProfileFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'withholding_enabled' => 'boolean',
            'withholding_rate' => 'decimal:2',
            'withholding_base_percent' => 'decimal:2',
            'enasarco_enabled' => 'boolean',
            'enasarco_mandate_type' => EnasarcoMandateType::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Referent, $this>
     */
    public function referent(): BelongsTo
    {
        return $this->belongsTo(Referent::class);
    }

    /**
     * @return BelongsTo<VatRate, $this>
     */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
