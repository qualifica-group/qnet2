<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\WorkOrder;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;

/**
 * Links the not-yet-linked work orders of a linked category whose class
 * select (the attribute mapped on `label`) holds an edition of the local
 * catalogue: the match is the edition id only (spec 0426). Run by every
 * catalogue sync; pending() tells the status page why the rest is still
 * unlinked. A work order saved in the app is linked at once by
 * FollowClassSelect: this covers the ones written before their class was
 * in the catalogue.
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
     * @return array{linked: int, unmatched: int}
     */
    public function link(): array
    {
        $counts = ['linked' => 0, 'unmatched' => 0];
        $catalogued = $this->cataloguedEditionIds();

        $this->eachUnlinked(function (WorkOrder $workOrder, ?int $editionId) use ($catalogued, &$counts): void {
            if ($editionId === null) {
                return;
            }

            if (isset($catalogued[$editionId])) {
                FormalabWorkOrderLink::query()->create(['work_order_id' => $workOrder->id, 'edition_id' => $editionId]);
                $counts['linked']++;
            } else {
                $counts['unmatched']++;
            }
        });

        return $counts;
    }

    /**
     * Why the unlinked work orders of the linked categories are still
     * unlinked, without linking anything: `linkable` ones wait for the next
     * catalogue sync, `unmatched` chose an edition missing from the
     * catalogue, `without_course_name` chose no class.
     *
     * @return array{linkable: int, unmatched: int, without_course_name: int}
     */
    public function pending(): array
    {
        $counts = ['linkable' => 0, 'unmatched' => 0, 'without_course_name' => 0];
        $catalogued = $this->cataloguedEditionIds();

        $this->eachUnlinked(function (WorkOrder $workOrder, ?int $editionId) use ($catalogued, &$counts): void {
            $reason = match (true) {
                $editionId === null => 'without_course_name',
                isset($catalogued[$editionId]) => 'linkable',
                default => 'unmatched',
            };
            $counts[$reason]++;
        });

        return $counts;
    }

    /**
     * Calls $visit with every unlinked work order of a linked category and
     * the edition chosen in its class select (null: none).
     *
     * @param  callable(WorkOrder, int|null): void  $visit
     */
    private function eachUnlinked(callable $visit): void
    {
        // The class select depends only on the work order's categories (in line order): resolved once per combination
        $keyCodes = [];

        WorkOrder::query()
            ->with('quoteLines.product.category')
            ->whereHas('quoteLines.product', fn ($query) => $query->whereIn('category_id', $this->maps->enabledCategoryIds()))
            ->whereNotIn('id', FormalabWorkOrderLink::query()->select('work_order_id'))
            ->chunkById(self::CHUNK, function ($workOrders) use ($visit, &$keyCodes): void {
                foreach ($workOrders as $workOrder) {
                    $categories = $workOrder->quoteLines->pluck('product.category.id')->filter()->unique()->implode(',');

                    if (! array_key_exists($categories, $keyCodes)) {
                        $keyCodes[$categories] = $this->courseWriter->keyCodeOf($workOrder);
                    }

                    $visit($workOrder, $this->courseWriter->editionIdAt($workOrder, $keyCodes[$categories]));
                }
            });
    }

    /**
     * @return array<int, true> edition id => true
     */
    private function cataloguedEditionIds(): array
    {
        return FormalabClass::query()->pluck('edition_id')->mapWithKeys(static fn (int $id): array => [$id => true])->all();
    }
}
