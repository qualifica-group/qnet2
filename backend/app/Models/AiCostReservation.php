<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reserved maximum cost of a model call that is still open (spec 0433, D-7).
 */
#[Fillable(['period', 'user_id', 'amount_usd'])]
class AiCostReservation extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['user_id' => 'int', 'amount_usd' => 'decimal:12', 'created_at' => 'datetime'];
    }
}
