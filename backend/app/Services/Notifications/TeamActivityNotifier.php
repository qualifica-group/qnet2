<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\TeamActivityEventEnum;
use App\Enums\TeamActivityRecordEnum;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TeamActivityNotification;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use App\Support\Notifications\RecordDetails;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * "Your team created / moved an Offerta, a Contratto, a Commessa" (spec
 * 0413): the actor's org-chart managers, at any level, who hold the
 * module's `notifyTeam` permission are told. Called explicitly by the
 * writers (no model observers, constraint since spec 0077).
 *
 * Silent for system writes, like TableRowNotifier: under
 * AssignmentNotifier::muted() (legacy migration, realign, imports) or with
 * nobody behind the write. Sent once the write is durable, actor excluded.
 */
final class TeamActivityNotifier
{
    public function __construct(private readonly TimeEntrySubordinateResolver $hierarchy) {}

    public function created(Quote|Contract|WorkOrder $record, ?User $actor = null): void
    {
        $this->notify($record, TeamActivityEventEnum::Created, null, $actor);
    }

    /**
     * No-op when the status did not actually change.
     */
    public function statusChanged(Quote|Contract|WorkOrder $record, ?int $previousStatusId, ?User $actor = null): void
    {
        $currentStatusId = $record->getAttribute(TeamActivityRecordEnum::of($record)->statusColumn());

        if ($currentStatusId === null || (int) $currentStatusId === $previousStatusId) {
            return;
        }

        $this->notify($record, TeamActivityEventEnum::StatusChanged, $previousStatusId, $actor);
    }

    private function notify(Quote|Contract|WorkOrder $record, TeamActivityEventEnum $event, ?int $previousStatusId, ?User $actor): void
    {
        // Step 1: system writes stay silent; the actor is whoever is logged in when the writer has none.
        $actor ??= Auth::user();

        if (AssignmentNotifier::isMuted() || ! $actor instanceof User) {
            return;
        }

        $kind = TeamActivityRecordEnum::of($record);

        // Step 2: once the write is durable, the facts and the recipients as they are then.
        DB::afterCommit(function () use ($record, $kind, $event, $previousStatusId, $actor): void {
            $recipients = $this->recipients($kind, $actor);

            if ($recipients->isEmpty()) {
                return;
            }

            $subject = $record instanceof Contract ? $record->quote()->firstOrFail() : $record;

            Notification::send($recipients, new TeamActivityNotification(
                record: $kind,
                event: $event,
                recordId: (int) $record->getKey(),
                recordLabel: RecordDetails::labelFor($subject),
                actorName: $actor->name,
                fromStatus: $kind->statusName($previousStatusId),
                toStatus: $kind->statusName($record->getAttribute($kind->statusColumn())),
                details: RecordDetails::for($subject),
            ));
        });
    }

    /**
     * The active managers above the actor holding the module's permission
     * through a role or directly. A query on the permission rows, not
     * `can()`: super-admin passes every gate and would be paged for every team.
     *
     * @return Collection<int, User>
     */
    private function recipients(TeamActivityRecordEnum $kind, User $actor): Collection
    {
        $managerIds = $this->hierarchy->allAncestorIds([$actor->id]);

        if ($managerIds === []) {
            return new Collection;
        }

        $holdsPermission = static fn (Builder $permissions): Builder => $permissions->where('name', $kind->permission());

        return User::query()
            ->whereKey($managerIds)
            ->whereKeyNot($actor->id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereHas('permissions', $holdsPermission)
                ->orWhereHas('roles.permissions', $holdsPermission))
            ->get();
    }
}
