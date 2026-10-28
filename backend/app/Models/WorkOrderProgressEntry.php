<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderProgressEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One manual progress record of a commessa (spec 0321): the INCREMENT
 * (0..100) recorded on `recorded_on`, like the legacy `avanzamento_commesse`.
 * The progress of a work order is the SUM of its entries.
 */
#[Fillable([
    'work_order_id',
    'percentage',
    'recorded_on',
    'created_by',
])]
class WorkOrderProgressEntry extends BaseModel
{
    /** @use HasFactory<WorkOrderProgressEntryFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'int',
            'recorded_on' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
