<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\EnasarcoParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * ENASARCO rates and ceilings of one year (spec 0213, D-3). The year is the
 * one of a settlement's period_to; the values are data entered by the user,
 * never constants in the code.
 */
#[Fillable([
    'year', 'total_rate', 'agent_share_percent', 'single_firm_ceiling', 'multi_firm_ceiling', 'notes',
])]
class EnasarcoParameter extends BaseModel
{
    /** @use HasFactory<EnasarcoParameterFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_rate' => 'decimal:2',
            'agent_share_percent' => 'decimal:2',
            'single_firm_ceiling' => 'decimal:2',
            'multi_firm_ceiling' => 'decimal:2',
        ];
    }
}
