<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\WorkOrder;
use App\Support\PeopleChangeLog;
use Illuminate\Support\Facades\DB;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * Removes the classes Formalab no longer has (spec 0426 D-12): the links of
 * their work orders are deleted, the class select of those work orders is
 * emptied (with the usual activity entry), and the class leaves the local
 * catalogue. The other mapped fields keep their last values.
 */
final class RemovedClassCleanup
{
    private const int CHUNK = 200;

    public function __construct(private readonly CourseFieldWriter $courseWriter) {}

    /**
     * @param  list<int>  $editionIds
     * @return array{classes: int, work_orders: int}
     */
    public function handle(array $editionIds): array
    {
        $counts = ['classes' => 0, 'work_orders' => 0];

        foreach (array_chunk(array_values(array_unique($editionIds)), self::CHUNK) as $chunk) {
            DB::transaction(function () use ($chunk, &$counts): void {
                // Step 1: unlink the work orders and empty their class select
                foreach (FormalabWorkOrderLink::query()->whereIn('edition_id', $chunk)->get() as $link) {
                    $link->delete();
                    $this->emptyClassSelect($link->work_order_id, $link->edition_id);
                    $counts['work_orders']++;
                }

                // Step 2: the class leaves the catalogue
                $counts['classes'] += FormalabClass::query()->whereIn('edition_id', $chunk)->delete();
            });
        }

        return $counts;
    }

    private function emptyClassSelect(int $workOrderId, int $editionId): void
    {
        $workOrder = WorkOrder::query()->find($workOrderId);
        $code = $workOrder === null ? null : $this->courseWriter->keyCodeOf($workOrder);

        if ($code === null || (int) ($workOrder->attribute_values[$code] ?? 0) !== $editionId) {
            return;
        }

        $captured = PeopleChangeLog::capture($workOrder);
        $values = $workOrder->attribute_values;
        unset($values[$code]);
        $workOrder->forceFill(['attribute_values' => $values])->save();
        PeopleChangeLog::log($captured);
    }
}
