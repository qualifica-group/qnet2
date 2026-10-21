<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\ContractTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Contract type catalogue entry (spec 0290 D-1). A type used by a contractual
 * condition is deactivated, never deleted.
 */
#[Fillable(['name', 'is_active', 'sort_order'])]
class ContractType extends BaseModel
{
    /** @use HasFactory<ContractTypeFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<EmploymentContract, $this>
     */
    public function employmentContracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class);
    }
}
