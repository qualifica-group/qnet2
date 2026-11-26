<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Month counter of the spent amount: one row per person, plus the company total (user_id 0).
 * Reservations lock these rows.
 */
#[Fillable(['period', 'user_id', 'spent_usd'])]
class AiSpendCounter extends Model
{
    public const int TOTAL_USER_ID = 0;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['user_id' => 'int', 'spent_usd' => 'decimal:12'];
    }
}
