<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\WorkOrderPaymentStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Payment status of a commessa line (spec 0201, D-2): a configurable lookup
 * (name/description/color/is_active) with `allows_delivery`, the flag that
 * marks the statuses meaning "si puo' consegnare" (it drives the notification
 * of D-4). `old_id` is the legacy `stato_pagamento` key the import resolves
 * through: deliberately NOT fillable, written only by the reference seeder.
 * `sort_order` is server-managed (WorkOrderPaymentStatusOrderManager).
 */
#[Fillable(['name', 'description', 'color', 'sort_order', 'is_active', 'allows_delivery'])]
class WorkOrderPaymentStatus extends BaseModel
{
    /** @use HasFactory<WorkOrderPaymentStatusFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_active' => 'bool',
            'allows_delivery' => 'bool',
        ];
    }

    /**
     * The line payments in this status: the referenced-by set the delete
     * guard of WorkOrderPaymentStatusService checks.
     *
     * @return HasMany<WorkOrderLinePayment, $this>
     */
    public function linePayments(): HasMany
    {
        return $this->hasMany(WorkOrderLinePayment::class);
    }
}
