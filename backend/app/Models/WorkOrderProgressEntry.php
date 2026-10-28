<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderProgressEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One manual progress record of a commessa (spec 0321): the CUMULATIVE
 * percentage reached on `recorded_on` (D-5). Entries of a work order are
 * strictly increasing, so the latest is the one with the highest percentage.
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
