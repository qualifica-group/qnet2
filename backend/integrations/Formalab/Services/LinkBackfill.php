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
 * skipped and counted. Run by every catalogue sync (spec 0423 D-15);
 * pending() tells the status page why the rest is still unlinked.
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
        $counts = ['linked' => 0, 'ambiguous' => 0, 'unmatched' => 0];

        $this->eachUnlinked(function (WorkOrder $workOrder, ?array $editions) use (&$counts): void {
            if ($editions === null) {
                return;
            }

            if (count($editions) === 1) {
                FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => $editions[0]]);
                $counts['linked']++;
            } else {
                $counts[$editions === [] ? 'unmatched' : 'ambiguous']++;
            }
        });

        return $counts;
    }

    /**
     * Why the unlinked work orders of the linked categories are still
     * unlinked, without linking anything: `linkable` ones wait for the next
     * catalogue sync, the others need their course name or the class fixed.
     *
     * @return array{linkable: int, ambiguous: int, unmatched: int, without_course_name: int}
     */
    public function pending(): array
    {
        $counts = ['linkable' => 0, 'ambiguous' => 0, 'unmatched' => 0, 'without_course_name' => 0];

        $this->eachUnlinked(function (WorkOrder $workOrder, ?array $editions) use (&$counts): void {
            $reason = match (true) {
                $editions === null => 'without_course_name',
                $editions === [] => 'unmatched',
                count($editions) === 1 => 'linkable',
                default => 'ambiguous',
            };
            $counts[$reason]++;
        });

        return $counts;
    }

    /**
     * Calls $visit with every unlinked work order of a linked category and
     * the catalogue editions whose label equals its course name (null: no
     * course name).
     *
     * @param  callable(WorkOrder, list<int>|null): void  $visit
     */
    private function eachUnlinked(callable $visit): void
    {
        // Step 1: label => edition ids of the catalogue
        $editionsByLabel = $this->editionsByLabel();

        // Step 2: every unlinked work order of a linked category, with the editions of its course name
        WorkOrder::query()
            ->whereHas('quoteLines.product', fn ($query) => $query->whereIn('category_id', $this->maps->enabledCategoryIds()))
            ->whereNotIn('id', FormalabWorkOrderLink::query()->select('work_order_id'))
            ->chunkById(self::CHUNK, function ($workOrders) use ($editionsByLabel, $visit): void {
                foreach ($workOrders as $workOrder) {
                    $label = $this->courseWriter->courseNameOf($workOrder);
                    $visit($workOrder, $label === null ? null : ($editionsByLabel[$label] ?? []));
                }
            });
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
