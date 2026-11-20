<?php

declare(strict_types=1);

namespace Integrations\Formalab\Models;

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The Formalab edition a work order is linked to. The relations live here,
 * on the integration side only: the core models know nothing about it.
 */
#[Fillable(['work_order_id', 'edition_id', 'linked_by', 'synced_hash', 'synced_at', 'sync_error'])]
class FormalabWorkOrderLink extends Model
{
    protected $table = 'formalab_work_order_links';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_order_id' => 'integer',
            'edition_id' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormalabClass, $this>
     */
    public function formalabClass(): BelongsTo
    {
        return $this->belongsTo(FormalabClass::class, 'edition_id', 'edition_id');
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
