<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\PurchaseOrderStatusLabelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Presentation of one PurchaseOrderStatus enum case (spec 0248, D-1/D-2): name,
 * badge color and icon, and the display order. Pure presentation: the enum
 * keeps all the logic, so no code decides anything from these columns.
 *
 * `system_key` (the enum value) is DELIBERATELY absent from #[Fillable]: written
 * only by the migration, never by a request. Rows are neither created nor
 * deleted by users.
 */
#[Fillable(['name', 'color', 'icon', 'sort_order'])]
class PurchaseOrderStatusLabel extends BaseModel
{
    /** @use HasFactory<PurchaseOrderStatusLabelFactory> */
    use HasFactory, LogsModelActivity;

    protected $table = 'purchase_order_statuses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'int'];
    }
}
