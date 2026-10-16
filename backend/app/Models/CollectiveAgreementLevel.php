<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CollectiveAgreementLevelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A level of a collective agreement (spec 0272 D-3), pointed to by
 * employment contracts.
 */
#[Fillable(['collective_agreement_id', 'code', 'description', 'sort_order'])]
class CollectiveAgreementLevel extends BaseModel
{
    /** @use HasFactory<CollectiveAgreementLevelFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<CollectiveAgreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(CollectiveAgreement::class, 'collective_agreement_id');
    }

    /**
     * @return HasMany<EmploymentContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }
}
