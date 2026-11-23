<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\WorkOrder;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * Links the not-yet-linked work orders of a linked category whose course name
 * (the attribute mapped on `label`, spec 0423) equals the label of exactly
 * one catalogue class. A label shared by several classes is ambiguous:
 * skipped and counted. Run by every catalogue sync (spec 0423 D-15).
 */
final class LinkBackfill
{
    private const int CHUNK = 500;

    public function __construct(
        private readonly CategoryFieldMapResolver $maps,
        private readonly CourseFieldWriter $courseWriter,
    ) {}

    /**
     * Only records the links: the fields are written by the next propagation
     * (a new link has never synced, so it is pending).
     *
     * @return array{linked: int, ambiguous: int, unmatched: int}
     */
    public function link(): array
    {
        // Step 1: label => edition ids of the catalogue
        $editionsByLabel = $this->editionsByLabel();
        $counts = ['linked' => 0, 'ambiguous' => 0, 'unmatched' => 0];

        // Step 2: classify every unlinked work order of a linked category that has a course name
        $categoryIds = $this->maps->enabledCategoryIds();

        WorkOrder::query()
            ->whereHas('quoteLines.product', static fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->whereNotIn('id', FormalabWorkOrderLink::query()->select('work_order_id'))
            ->chunkById(self::CHUNK, function ($workOrders) use ($editionsByLabel, &$counts): void {
                foreach ($workOrders as $workOrder) {
                    $label = $this->courseWriter->courseNameOf($workOrder);

                    if ($label === null) {
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
