<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\ResolvedTimeEntryLinks;
use App\DataObjects\TimeEntries\TimeEntryData;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `time-entries` resource (spec 0122, MT-B2).
 *
 * Thin on purpose: the only write-time rule that a FormRequest structurally
 * cannot enforce is D-5 (the link/title override), delegated whole to
 * TimeEntryLinkResolver — see its own docblock for why. Everything else
 * (ownership, permissions) is decided by the controller before either
 * method here ever runs. Every write also mirrors `notes` as a comment on
 * the linked commessa (WorkOrderNoteSynchronizer), in the same transaction
 * as the TimeEntry row.
 *
 * @see TimeEntryLinkResolver
 * @see WorkOrderNoteSynchronizer
 */
final class TimeEntryService
{
    /**
     * Relations eager-loaded for TimeEntryResource, so a single request
     * never N+1s (preventLazyLoading, backend.md §3). PUBLIC (MT-B3): the
     * list/stats read path (`TimeEntryDaySetBuilder`) eager-loads its own
     * `TimeEntry` query with this SAME set, so `TimeEntryResource`/the
     * pulse task-type clustering never risk drifting from what this class
     * loads for store/show/update.
     *
     * @var array<int, string>
     */
    public const array DETAIL_RELATIONS = ['user', 'taskType', 'registry', 'opportunity', 'workOrder', 'task'];

    public function __construct(
        private readonly TimeEntryLinkResolver $linkResolver,
        private readonly WorkOrderNoteSynchronizer $workOrderNoteSynchronizer,
    ) {}

    /**
     * Create a TimeEntry for $owner (data_contract POST). $owner is
     * resolved by the controller — the authenticated actor by default, or
     * an explicit `user_id` gated by `time-entries.manageAll` (AC-007).
     */
    public function create(TimeEntryData $data, User $owner): TimeEntry
    {
        // Step 1: D-5 — resolve title/registry/opportunity/work_order/task,
        // BEFORE the row exists, so a refusal creates nothing.
        $links = $this->linkResolver->resolve($data, $owner);

        // Step 2: build, with the owner taken from the caller rather than
        // the payload.
        $entry = new TimeEntry($data->attributes());
        $entry->user_id = $owner->id;
        $this->applyLinks($entry, $links);

        // Step 3: mirror the note as a commessa comment and persist.
        $this->persist($entry);

        return $this->loadDetail($entry);
    }

    /**
     * Update a TimeEntry (data_contract PUT — a full replace, not a partial
     * PATCH, see UpdateTimeEntryRequest). The owner never changes on update
     * (`user_id` is `prohibited`), so D-5's Task-visibility check keeps
     * running against the entry's OWN (immutable) user, never the acting
     * admin on a manageAll edit of someone else's row.
     */
    public function update(TimeEntry $entry, TimeEntryData $data): TimeEntry
    {
        $owner = User::query()->findOrFail($entry->user_id);

        // Step 1: D-5 again, against the RESULTING payload.
        $links = $this->linkResolver->resolve($data, $owner);

        // Step 2: apply.
        $entry->fill($data->attributes());
        $this->applyLinks($entry, $links);

        // Step 3: re-sync the commessa comment (changed text, changed or
        // cleared commessa) and persist.
        $this->persist($entry);

        return $this->loadDetail($entry);
    }

    public function delete(TimeEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $this->workOrderNoteSynchronizer->detach($entry);
            $entry->delete();
        });
    }

    public function loadDetail(TimeEntry $entry): TimeEntry
    {
        return $entry->load(self::DETAIL_RELATIONS);
    }

    private function persist(TimeEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $this->workOrderNoteSynchronizer->sync($entry);
            $entry->save();
        });
    }

    private function applyLinks(TimeEntry $entry, ResolvedTimeEntryLinks $links): void
    {
        $entry->title = $links->title;
        $entry->registry_id = $links->registryId;
        $entry->opportunity_id = $links->opportunityId;
        $entry->work_order_id = $links->workOrderId;
        $entry->task_id = $links->taskId;
    }
}
