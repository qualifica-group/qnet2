<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0322 amendment: the two closing system rows become "Chiusa con esito
 * positivo" (`completed`) and "Chiusa con esito negativo" (`closed`), the row
 * a force-closed commessa lands on. Only rows still carrying the old default
 * name are renamed, so a name changed by hand survives.
 *
 * Every force-closed commessa still on the global `open` placeholder of the
 * previous migration moves onto the global `closed` row; the resolver remaps
 * it by system key onto its own set at the next resolution.
 */
return new class extends Migration
{
    private const array RENAMES = [
        'completed' => ['Completata', 'Chiusa con esito positivo'],
        'closed' => ['Chiusa', 'Chiusa con esito negativo'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $systemKey => [$oldName, $newName]) {
            $this->rename($systemKey, $oldName, $newName);
        }

        $this->moveForceClosedWorkOrders('open', 'closed');
    }

    public function down(): void
    {
        $this->moveForceClosedWorkOrders('closed', 'open');

        foreach (self::RENAMES as $systemKey => [$oldName, $newName]) {
            $this->rename($systemKey, $newName, $oldName);
        }
    }

    private function rename(string $systemKey, string $from, string $to): void
    {
        DB::table('work_order_workflow_statuses')
            ->where('system_key', $systemKey)
            ->where('name', $from)
            ->update(['name' => $to]);
    }

    private function moveForceClosedWorkOrders(string $fromKey, string $toKey): void
    {
        $fromId = $this->globalSystemRowId($fromKey);
        $toId = $this->globalSystemRowId($toKey);

        if ($fromId === null || $toId === null) {
            return;
        }

        DB::table('work_orders')
            ->where('is_force_closed', true)
            ->where('work_order_workflow_status_id', $fromId)
            ->update(['work_order_workflow_status_id' => $toId]);
    }

    private function globalSystemRowId(string $systemKey): ?int
    {
        $id = DB::table('work_order_workflow_statuses')
            ->whereNull('work_order_workflow_id')
            ->where('system_key', $systemKey)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
};
