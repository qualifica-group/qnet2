<?php

namespace Database\Seeders;

use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot realigning the progress of the migrated commesse that the legacy changed
 * after the migration: `php artisan db:seed --class=WorkOrderProgressAlignmentSeeder --force`.
 *
 * The progress source only creates entries and skips a commessa that already has
 * some, and a legacy deletion never reaches qnet-2: 37 commesse kept a different
 * percentage (e.g. legacy 12574 at 100%, qnet-2 at 37%). The rows below are the
 * legacy `avanzamento_commesse` of each one (legacy order id => [percentage,
 * recorded_on] in id order), read on 2026-11-24; the two legacy rows dated
 * 0000-00-00 (10678, 15903) take the day they were typed. 20140 has none left.
 *
 * Only a commessa whose entries all came from the migration (created_by null) is
 * replaced; one with an entry typed in qnet-2 is left untouched. Re-running it
 * changes nothing. Written without activity log, like the import.
 */
class WorkOrderProgressAlignmentSeeder extends Seeder
{
    /** @var array<int, list<array{0: int, 1: string}>> */
    private const array LEGACY_ENTRIES = [
        768 => [[5, '2026-09-01'], [55, '2026-11-04']],
        4791 => [[30, '2026-06-17'], [50, '2026-09-18'], [0, '2026-11-05'], [10, '2026-11-05'], [4, '2026-11-05'], [1, '2026-11-05'], [1, '2026-11-05'], [4, '2026-11-05'], [0, '2026-11-05'], [0, '2026-11-05'], [0, '2026-11-05']],
        6996 => [[30, '2026-07-20'], [50, '2026-07-27'], [20, '2026-11-05'], [0, '2026-11-05'], [0, '2026-11-05']],
        7214 => [[50, '2026-10-26'], [45, '2026-11-03'], [5, '2026-11-05']],
        7493 => [[30, '2026-08-04'], [50, '2026-09-17'], [15, '2026-10-12'], [5, '2026-10-30'], [0, '2026-11-03'], [0, '2026-11-03']],
        7604 => [[30, '2026-07-01'], [48, '2026-11-05']],
        8388 => [[5, '2026-10-22'], [45, '2026-11-09']],
        8681 => [[30, '2026-07-01'], [50, '2026-11-05']],
        9310 => [[80, '2026-09-28'], [15, '2026-10-28'], [1, '2026-11-03'], [4, '2026-11-03']],
        9363 => [[30, '2026-07-16'], [50, '2026-09-30'], [15, '2026-11-02'], [1, '2026-11-05'], [4, '2026-11-05']],
        9643 => [[5, '2026-09-15'], [25, '2026-11-04'], [50, '2026-11-05']],
        10678 => [[1, '2025-01-31'], [1, '2025-02-28'], [8, '2025-04-03']],
        11696 => [[30, '2026-11-03'], [50, '2026-11-05']],
        11836 => [[30, '2026-09-11'], [50, '2026-09-29'], [16, '2026-11-02']],
        12574 => [[100, '2025-10-31']],
        13185 => [[5, '2026-09-01'], [55, '2026-10-16'], [30, '2026-10-30']],
        13260 => [[30, '2026-07-16'], [50, '2026-09-08'], [0, '2026-11-05'], [10, '2026-11-05'], [4, '2026-11-05'], [2, '2026-11-05'], [4, '2026-11-05'], [0, '2026-11-05']],
        13542 => [[10, '2026-03-11'], [10, '2026-03-11'], [10, '2026-03-11'], [0, '2026-03-11'], [20, '2026-03-11'], [30, '2026-10-07'], [20, '2026-11-02'], [0, '2026-11-03'], [0, '2026-11-03']],
        13999 => [[5, '2026-05-28'], [75, '2026-08-06'], [15, '2026-10-26'], [5, '2026-10-29'], [0, '2026-11-03'], [0, '2026-11-03']],
        14248 => [[10, '2026-09-21'], [40, '2026-11-06']],
        14454 => [[5, '2026-06-09'], [75, '2026-10-30']],
        15903 => [[100, '2026-05-14']],
        16431 => [[2, '2026-02-04'], [4, '2026-03-13'], [0, '2026-04-14'], [0, '2026-04-14'], [0, '2026-04-14'], [14, '2026-04-14'], [0, '2026-04-14'], [80, '2026-11-02']],
        16550 => [[10, '2026-08-07'], [20, '2026-09-21'], [50, '2026-11-03']],
        16992 => [[2, '2026-03-09'], [0, '2026-03-09'], [0, '2026-03-09'], [18, '2026-03-09'], [0, '2026-03-30'], [55, '2026-10-30']],
        17217 => [[10, '2026-04-03'], [90, '2026-06-23']],
        17550 => [[5, '2026-05-06'], [0, '2026-10-29'], [5, '2026-10-29'], [70, '2026-10-29']],
        17573 => [[2, '2026-04-27'], [6, '2026-06-17'], [40, '2026-10-30']],
        17774 => [[8, '2026-05-21'], [72, '2026-10-30']],
        18132 => [[70, '2026-07-23'], [10, '2026-10-19'], [10, '2026-10-27'], [5, '2026-11-03']],
        18168 => [[2, '2026-06-29'], [6, '2026-10-30']],
        18674 => [[5, '2026-09-10'], [73, '2026-10-30']],
        18764 => [[30, '2026-09-15'], [50, '2026-09-17'], [0, '2026-11-03'], [20, '2026-11-03'], [0, '2026-11-03']],
        19714 => [[80, '2026-09-29'], [20, '2026-11-03']],
        20054 => [[10, '2026-10-27'], [10, '2026-10-27'], [10, '2026-10-27'], [0, '2026-10-27'], [20, '2026-10-27'], [20, '2026-10-27'], [8, '2026-10-27'], [2, '2026-10-27'], [0, '2026-10-27'], [10, '2026-10-27'], [4, '2026-10-27'], [2, '2026-10-27'], [4, '2026-10-27'], [0, '2026-10-27']],
        20055 => [[10, '2026-10-27'], [10, '2026-10-27'], [10, '2026-10-27'], [0, '2026-10-27'], [20, '2026-10-27'], [20, '2026-10-27'], [8, '2026-10-27'], [2, '2026-10-27'], [0, '2026-10-27'], [10, '2026-10-27'], [4, '2026-10-27'], [2, '2026-10-27'], [4, '2026-10-27'], [0, '2026-10-27']],
        20140 => [],
    ];

    public function run(): void
    {
        $aligned = 0;
        $skipped = [];

        activity()->withoutLogs(function () use (&$aligned, &$skipped): void {
            foreach (self::LEGACY_ENTRIES as $legacyId => $entries) {
                $workOrder = WorkOrder::query()->where('old_id', $legacyId)->first();

                if ($workOrder === null || $this->hasEntriesTypedInQnet($workOrder)) {
                    $skipped[] = $legacyId;

                    continue;
                }

                $aligned += $this->replaceEntries($workOrder, $entries) ? 1 : 0;
            }
        });

        $this->command?->info("Avanzamento allineato al legacy: {$aligned} commesse.");

        if ($skipped !== []) {
            $this->command?->warn('Saltate (non migrate o con avanzamenti inseriti in qnet-2): '.implode(', ', $skipped));
        }
    }

    private function hasEntriesTypedInQnet(WorkOrder $workOrder): bool
    {
        return $workOrder->progressEntries()->whereNotNull('created_by')->exists();
    }

    /**
     * @param  list<array{0: int, 1: string}>  $entries
     */
    private function replaceEntries(WorkOrder $workOrder, array $entries): bool
    {
        $current = $workOrder->progressEntries()->get()
            ->map(fn (WorkOrderProgressEntry $entry): array => [$entry->percentage, $entry->recorded_on->toDateString()])
            ->all();

        if ($current === $entries) {
            return false;
        }

        DB::transaction(function () use ($workOrder, $entries): void {
            $workOrder->progressEntries()->delete();

            foreach ($entries as [$percentage, $recordedOn]) {
                $workOrder->progressEntries()->create(['percentage' => $percentage, 'recorded_on' => $recordedOn, 'created_by' => null]);
            }
        });

        return true;
    }
}
