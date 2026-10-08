<?php

namespace Database\Seeders;

use App\Models\WorkOrderPaymentStatus;
use Illuminate\Database\Seeder;

/**
 * Seed the ten legacy payment statuses of a commessa (spec 0201, D-2). Like
 * ProductTypologySeeder this is CLEAN reference data: no `Demo` prefix, called
 * from DatabaseSeeder, because a production install needs them. `old_id` is the
 * legacy `Manageorder::$stato_pagamenti` key (1..10) the line-payment migration
 * resolves through; `allows_delivery` is true for the keys the legacy notified
 * on (1, 2, 3, 6). Idempotent: matched by `old_id`, so a manual rename or
 * recolor made from the settings module is never overwritten on re-run.
 */
class WorkOrderPaymentStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{0: int, 1: string, 2: string, 3: bool}> old_id, name, color token, allows_delivery
     */
    private const array STATUSES = [
        [1, 'Verde (Omaggio)', 'green', true],
        [2, 'Verde (Omaggio, si può consegnare)', 'green', true],
        [3, 'Verde (Saldato, si può consegnare)', 'green', true],
        [4, 'Giallo (Iniziare la lavorazione)', 'yellow', false],
        [5, 'Arancione (Pagato solo acconto)', 'orange', false],
        [6, 'Blu (Accordi sul pagamento, si può consegnare)', 'blue', true],
        [7, 'Giallo Rosso (Insoluto anno precedente)', 'amber', false],
        [8, 'Giallo Rosso (Insoluti altro standard)', 'amber', false],
        [9, 'Rosso (Insoluto)', 'red', false],
        [10, 'Arancione (Saldo dell\'acconto)', 'orange', false],
    ];

    private const int SORT_STEP = 10;

    public function run(): void
    {
        foreach (self::STATUSES as [$oldId, $name, $color, $allowsDelivery]) {
            if (WorkOrderPaymentStatus::query()->where('old_id', $oldId)->exists()) {
                continue;
            }

            // old_id is not mass-assignable (never client-writable): forceFill.
            (new WorkOrderPaymentStatus)->forceFill([
                'old_id' => $oldId,
                'name' => $name,
                'color' => $color,
                'sort_order' => $oldId * self::SORT_STEP,
                'is_active' => true,
                'allows_delivery' => $allowsDelivery,
            ])->save();
        }
    }
}
