<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One call to the model (summaries included) in the cost ledger (spec 0433, D-10). Deliberately
 * no relation to User or AiConversation: the row outlives both.
 */
#[Fillable([
    'user_id', 'conversation_id', 'model_id', 'provider',
    'input_tokens', 'output_tokens', 'cached_tokens', 'reasoning_tokens',
    'cost_usd', 'reserved_usd', 'signal', 'incurred_on',
])]
class AiCostEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'int',
            'conversation_id' => 'int',
            'input_tokens' => 'int',
            'output_tokens' => 'int',
            'cached_tokens' => 'int',
            'reasoning_tokens' => 'int',
            'cost_usd' => 'decimal:12',
            'reserved_usd' => 'decimal:12',
            'incurred_on' => 'date',
        ];
    }

    /**
     * @return HasMany<AiCostShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(AiCostShare::class);
    }
}
