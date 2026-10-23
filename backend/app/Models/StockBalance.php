<?php

namespace App\Models;

use App\Enums\StockHolder;
use App\Models\Abstracts\BaseModel;
use Database\Factories\StockBalanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity of a tracked product in an operational site, free (`user_id` null)
 * or assigned to a user (spec 0307, D-4). No #[Fillable]: every column is
 * written exclusively by App\Services\Stock\StockMovementService, which
 * assigns them explicitly, so a stray mass-assignment can never move stock.
 * `holder_key` is a generated column and is never written.
 */
class StockBalance extends BaseModel
{
    /** @use HasFactory<StockBalanceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'last_moved_on' => 'date:Y-m-d',
        ];
    }

    public function holder(): StockHolder
    {
        return $this->user_id === null ? StockHolder::Free : StockHolder::Assigned;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<OperationalSite, $this>
     */
    public function operationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
