<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\User;

/**
 * WHO receives a Task notification (spec 0119, D-3/D-4/D-5/D-6; spec 0153,
 * D-11/D-13). Pure: it holds four id sets and answers with the audience each
 * event asks for. It never queries — TaskNotifier resolves the sets once and
 * loads the User rows itself, in one query.
 *
 * The general rule, applied by resolve(): drop nulls, drop ONE named id
 * (D-3's actor, for most events), deduplicate (D-6 — record roles ACCUMULATE,
 * one user may be creator, requester and assignee at once, so a per-role
 * merge would send the same person three copies of one event). Two events
 * deviate from "drop the actor" on purpose (spec 0153, D-13):
 * `assigned()`/`restrictAssigned()` drop the CREATOR instead — a manager who
 * both requests and assigns a Task to themselves still hears about it; and
 * `watchers()`/`restrictWatchers()` drop NOBODY, not even the actor.
 */
final readonly class TaskNotificationAudience
{
    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, int>  $watcherIds
     */
    public function __construct(
        private int $creatorId,
        private ?int $requesterId,
        private array $assigneeIds,
        private array $watcherIds,
    ) {}

    /**
     * Reads the two pivots off an ALREADY LOADED Task. The caller is
     * responsible for `loadMissing(['assignees', 'watchers'])`:
     * `Model::preventLazyLoading()` is on outside production, so a forgotten
     * eager load fails loudly here instead of degrading into N+1 silently.
     */
    public static function of(Task $task): self
    {
        return new self(
            creatorId: (int) $task->creator_id,
            requesterId: $task->requester_id === null ? null : (int) $task->requester_id,
            assigneeIds: $task->assignees->pluck('id')->map(intval(...))->all(),
            watcherIds: $task->watchers->pluck('id')->map(intval(...))->all(),
        );
    }

    /**
     * Creator + requester + assignees + watchers: the audience of events
     * 9, 10 and 11. Since D-4 (spec 0119) resolved the document's own
     * contradiction about who hears about a completion in favour of the
     * UNION, `uncompleted()`/`locked()`/`unlocked()` still share this set —
     * only the CLOSING events (2/3) narrowed away from it (spec 0153, D-11,
     * see closure() below).
     *
     * @return array<int, int>
     */
    public function everyone(?User $actor): array
    {
        return $this->resolve(
            [$this->creatorId, $this->requesterId, ...$this->assigneeIds, ...$this->watcherIds],
            $actor?->id,
        );
    }

    /**
     * Events 4 and 5.
     *
     * @return array<int, int>
     */
    public function assignees(?User $actor): array
    {
        return $this->resolve($this->assigneeIds, $actor?->id);
    }

    /**
     * Event 7's OWN audience (spec 0153, D-13): every current assignee,
     * dropping the CREATOR rather than the actor.
     *
     * @return array<int, int>
     */
    public function assigned(): array
    {
        return $this->resolve($this->assigneeIds, $this->creatorId);
    }

    /**
     * Event 7's delta path (spec 0118 D-9's shape: an EXPLICIT set of
     * newly-added ids on a PATCH), same creator-only exclusion as assigned().
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function restrictAssigned(array $ids): array
    {
        return $this->resolve($ids, $this->creatorId);
    }

    /**
     * Event 8 (spec 0153, D-13): every current watcher, excluding NOBODY —
     * not even the actor.
     *
     * @return array<int, int>
     */
    public function watchers(): array
    {
        return $this->resolve($this->watcherIds, null);
    }

    /**
     * Event 8's delta path: the same "exclude nobody" rule as watchers().
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public function restrictWatchers(array $ids): array
    {
        return $this->resolve($ids, null);
    }

    /**
     * Event 1, the ONLY one where the requester is the sole intended
     * recipient — hence the ONLY place D-5 lets the creator stand in. Without
     * the fallback a validation request on one of the historical rows that
     * carry no `requester_id` (spec 0118 did not backfill them) would reach
     * nobody, and the Task would sit in validation forever.
     *
     * @return array<int, int>
     */
    public function requesterOrCreator(?User $actor): array
    {
        return $this->resolve([$this->requesterId ?? $this->creatorId], $actor?->id);
    }

    /**
     * Event 6. No D-5 fallback here: the creator is already a recipient in
     * their own right, so a null requester simply contributes nobody.
     *
     * @return array<int, int>
     */
    public function requesterAndCreator(?User $actor): array
    {
        return $this->resolve([$this->requesterId, $this->creatorId], $actor?->id);
    }

    /**
     * Events 2/3 (spec 0153, D-11): requester + watchers, plus every assignee
     * only when `$includeAssignees` (a single-assignee Task never tells its
     * lone assignee it is closing their own task). The creator is NEVER a
     * member of this set as such — only ever through the requester/watcher/
     * assignee role, exactly like every other audience. Inactive recipients
     * are dropped by TaskNotifier::send() (`activeOnly`), not here — this
     * class never queries.
     *
     * @return array<int, int>
     */
    public function closure(?User $actor, bool $includeAssignees): array
    {
        $ids = [$this->requesterId, ...$this->watcherIds];

        if ($includeAssignees) {
            $ids = [...$ids, ...$this->assigneeIds];
        }

        return $this->resolve($ids, $actor?->id);
    }

    /**
     * Drop nulls, drop `$excludeId` when present, deduplicate, and reindex so
     * the result is a list and not a map with holes.
     *
     * @param  array<int, int|null>  $ids
     * @return array<int, int>
     */
    private function resolve(array $ids, ?int $excludeId): array
    {
        $ids = array_filter($ids, static fn (?int $id): bool => $id !== null);

        if ($excludeId !== null) {
            $ids = array_filter($ids, static fn (int $id): bool => $id !== $excludeId);
        }

        return array_values(array_unique($ids));
    }
}
