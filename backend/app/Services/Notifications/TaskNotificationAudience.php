<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Task;
use App\Models\User;

/**
 * WHO receives a Task notification (spec 0119, D-3/D-4/D-5/D-6). Pure: it
 * holds four id sets and answers with the audience each event asks for. It
 * never queries — TaskNotifier resolves the sets once and loads the User rows
 * itself, in one query.
 *
 * Two rules apply to EVERY answer, which is the whole reason this class
 * exists instead of four inline array_merge():
 *
 *   D-3, the actor is always dropped. The product document never says so, but
 *   AssignmentNotifier already works this way ("nobody needs to be told what
 *   they just did") and it is the only precedent in the repo. Accepted
 *   consequence: the creator who blocks their own Task gets no TaskLocked.
 *
 *   D-6, ids are deduplicated. Record roles ACCUMULATE — one user may be
 *   creator, requester and assignee at once — so a per-role merge would send
 *   the same person three copies of one event.
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
     * 2, 3, 9, 10 and 11. Since D-4 resolved the document's own contradiction
     * about who hears about a completion in favour of the UNION, the closing
     * events share this set with the blocking ones.
     *
     * @return array<int, int>
     */
    public function everyone(?User $actor): array
    {
        return $this->resolve(
            [$this->creatorId, $this->requesterId, ...$this->assigneeIds, ...$this->watcherIds],
            $actor,
        );
    }

    /**
     * Events 4, 5 and 7.
     *
     * @return array<int, int>
     */
    public function assignees(?User $actor): array
    {
        return $this->resolve($this->assigneeIds, $actor);
    }

    /**
     * Event 8.
     *
     * @return array<int, int>
     */
    public function watchers(?User $actor): array
    {
        return $this->resolve($this->watcherIds, $actor);
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
        return $this->resolve([$this->requesterId ?? $this->creatorId], $actor);
    }

    /**
     * Event 6. No D-5 fallback here: the creator is already a recipient in
     * their own right, so a null requester simply contributes nobody.
     *
     * @return array<int, int>
     */
    public function requesterAndCreator(?User $actor): array
    {
        return $this->resolve([$this->requesterId, $this->creatorId], $actor);
    }

    /**
     * The same two rules applied to an EXPLICIT set of ids rather than to a
     * role: what D-9 needs on a PATCH, where the audience is not "the
     * assignees" but "the assignees that were just added".
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function restrict(array $ids, ?User $actor): array
    {
        return (new self(0, null, [], []))->resolve($ids, $actor);
    }

    /**
     * The two rules, applied in one place: drop nulls, drop the actor (D-3),
     * deduplicate (D-6), and reindex so the result is a list and not a map
     * with holes.
     *
     * @param  array<int, int|null>  $ids
     * @return array<int, int>
     */
    private function resolve(array $ids, ?User $actor): array
    {
        $ids = array_filter($ids, static fn (?int $id): bool => $id !== null);

        if ($actor !== null) {
            $ids = array_filter($ids, static fn (int $id): bool => $id !== $actor->id);
        }

        return array_values(array_unique($ids));
    }
}
