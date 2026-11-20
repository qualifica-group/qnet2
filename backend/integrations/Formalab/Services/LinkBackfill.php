<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\WorkOrder;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * Links the not-yet-linked work orders whose course name equals the label of
 * exactly one catalogue class. A label shared by several classes is
 * ambiguous: skipped and counted.
 */
final class LinkBackfill
{
    private const int CHUNK = 500;

    public function __construct(private readonly LinkPropagator $propagator) {}

    /**
     * @return array{linked: int, ambiguous: int, unmatched: int}
     */
    public function handle(): array
    {
        // Step 1: label => edition ids of the catalogue
        $editionsByLabel = $this->editionsByLabel();
        $counts = ['linked' => 0, 'ambiguous' => 0, 'unmatched' => 0];

        // Step 2: classify every unlinked work order that has a course name
        WorkOrder::query()
            ->whereNotNull('attribute_values->'.CourseFieldWriter::ELIGIBILITY_CODE)
            ->whereNotIn('id', FormalabWorkOrderLink::query()->select('work_order_id'))
            ->chunkById(self::CHUNK, function ($workOrders) use ($editionsByLabel, &$counts): void {
                foreach ($workOrders as $workOrder) {
                    $label = trim((string) ($workOrder->attribute_values[CourseFieldWriter::ELIGIBILITY_CODE] ?? ''));

                    if ($label === '') {
                        continue;
                    }

                    $editions = $editionsByLabel[$label] ?? [];

                    if (count($editions) === 1) {
                        FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => $editions[0]]);
                        $counts['linked']++;
                    } else {
                        $counts[$editions === [] ? 'unmatched' : 'ambiguous']++;
                    }
                }
            });

        // Step 3: the new links get the class fields right away
        if ($counts['linked'] > 0) {
            $this->propagator->propagate();
        }

        return $counts;
    }

    /**
     * @return array<string, list<int>>
     */
    private function editionsByLabel(): array
    {
        $map = [];

        foreach (FormalabClass::query()->get(['edition_id', 'label']) as $class) {
            $map[$class->label][] = $class->edition_id;
        }

        return $map;
    }
}
