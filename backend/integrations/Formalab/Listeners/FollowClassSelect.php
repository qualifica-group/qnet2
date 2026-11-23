<?php

declare(strict_types=1);

namespace Integrations\Formalab\Listeners;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Models\FormalabWorkOrderLink;
use Integrations\Formalab\Services\CourseFieldWriter;
use Integrations\Formalab\Services\LinkPropagator;

/**
 * Keeps a work order's Formalab link on the class chosen in its select
 * (spec 0426 D-5): on every save that touches the attribute values the link
 * is created, moved to the new edition or removed when the select is
 * cleared (or holds an edition the catalogue does not have, which the next
 * catalogue sync links once it arrives), and the mapped fields are written
 * at once from the local catalogue. A failure is recorded on the link, never thrown: the user's
 * save always goes through.
 */
final class FollowClassSelect
{
    public function __construct(
        private readonly CourseFieldWriter $courseWriter,
        private readonly LinkPropagator $propagator,
    ) {}

    public function handle(WorkOrder $workOrder): void
    {
        // Step 1: only a change of the attribute values can change the class
        if (! $workOrder->wasRecentlyCreated && ! $workOrder->wasChanged('attribute_values')) {
            return;
        }

        // Step 2: nothing to do when the link already points at the chosen edition
        $editionId = $this->courseWriter->editionIdOf($workOrder);
        $link = FormalabWorkOrderLink::query()->where('work_order_id', $workOrder->id)->first();

        if ($editionId === $link?->edition_id) {
            return;
        }

        // Step 3: a cleared select, or an edition missing from the catalogue, removes the link; the fields keep their last values
        if ($editionId === null || ! FormalabClass::query()->whereKey($editionId)->exists()) {
            $link?->delete();

            return;
        }

        // Step 4: point the link at the edition, then write its fields (nested saves find it aligned)
        $link ??= new FormalabWorkOrderLink(['work_order_id' => $workOrder->id]);
        $link->fill([
            'edition_id' => $editionId,
            'linked_by' => $link->linked_by ?? Auth::id(),
            'synced_hash' => null,
            'synced_at' => null,
            'sync_error' => null,
        ])->save();

        $this->propagator->syncLink($link->load('formalabClass'));
    }
}
