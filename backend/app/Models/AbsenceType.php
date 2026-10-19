<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\AbsenceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Absence type catalogue entry (spec 0278 D-5). A type used by a request is
 * deactivated, never deleted.
 */
#[Fillable(['name', 'deducts_balance', 'requires_approval', 'is_active', 'sort_order'])]
class AbsenceType extends BaseModel
{
    /** @use HasFactory<AbsenceTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deducts_balance' => 'boolean',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<AbsenceRequest, $this>
     */
    public function absenceRequests(): HasMany
    {
        return $this->hasMany(AbsenceRequest::class);
    }
}
