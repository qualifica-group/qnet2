<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Equal share of a ledger entry for one business function (null = "no function").
 */
#[Fillable(['ai_cost_entry_id', 'business_function_id', 'cost_usd'])]
class AiCostShare extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['business_function_id' => 'int', 'cost_usd' => 'decimal:12'];
    }
}
