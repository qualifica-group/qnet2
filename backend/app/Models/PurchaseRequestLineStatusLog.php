<?php

namespace App\Models;

use App\Enums\PurchaseRequestLineStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One status change of a purchase request line (spec 0208, D-9). Append-only:
 * rows are written by PurchaseRequestLineStatusService and never updated.
 */
#[Fillable(['purchase_request_line_id', 'user_id', 'from_status', 'to_status', 'reason', 'bulk_group_id'])]
class PurchaseRequestLineStatusLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'from_status' => PurchaseRequestLineStatus::class,
            'to_status' => PurchaseRequestLineStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PurchaseRequestLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestLine::class, 'purchase_request_line_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
