<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\CollectiveAgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Collective labour agreement (CCNL) catalogue entry (spec 0272 D-3).
 */
#[Fillable(['name', 'code'])]
class CollectiveAgreement extends BaseModel
{
    /** @use HasFactory<CollectiveAgreementFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return HasMany<CollectiveAgreementLevel, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(CollectiveAgreementLevel::class)->orderBy('sort_order')->orderBy('id');
    }
}
