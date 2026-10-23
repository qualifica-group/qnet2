<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Abstracts\BaseModel;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of the append-only stock register (spec 0307, D-11/D-15). No
 * #[Fillable] and no activity log: it is written only by
 * App\Services\Stock\StockMovementService and is itself the log.
 */
class StockMovement extends BaseModel
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'moved_on' => 'date:Y-m-d',
        ];
    }

    /**
     * Movements touching the site as source or destination.
     *
     * @param  Builder<StockMovement>  $query
     */
    public function scopeInvolvingSite(Builder $query, int $siteId): void
    {
        $query->where(fn (Builder $inner) => $inner->where('from_operational_site_id', $siteId)->orWhere('to_operational_site_id', $siteId));
    }

    /**
     * Movements the user took part in: source, destination or author.
     *
     * @param  Builder<StockMovement>  $query
     */
    public function scopeInvolvingUser(Builder $query, int $userId): void
    {
        $query->where(fn (Builder $inner) => $inner
            ->where('from_user_id', $userId)->orWhere('to_user_id', $userId)->orWhere('created_by', $userId));
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
    public function fromOperationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class, 'from_operational_site_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<OperationalSite, $this>
     */
    public function toOperationalSite(): BelongsTo
    {
        return $this->belongsTo(OperationalSite::class, 'to_operational_site_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
