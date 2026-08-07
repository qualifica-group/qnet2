<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\AssignmentRoleEnum;
use App\Models\User;
use App\Notifications\RecordAssignmentNotification;
use App\Support\Notifications\RecordDetails;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The ONE place that turns "these people were just put in charge of this
 * record" into notifications (spec 0081). Shared by RegistryService,
 * OpportunityService and the request-management writers so the exclusion
 * rules, the after-commit timing and the recipient query exist once instead
 * of once per module.
 *
 * Deliberately NOT an observer: this repo has no model observers/events
 * (constraint carried since spec 0077) and the transition it reports —
 * a pivot ATTACH — is invisible to Eloquent events anyway.
 */
final class AssignmentNotifier
{
    /**
     * @param  Model  $record  the Registry or Opportunity just written; its
     *                         label and detail card are derived here
     * @param  ?User  $actor  who performed the write; excluded from the
     *                        recipients, since nobody needs to be told what
     *                        they just did. Null for system-initiated writes
     *                        (imports): then nobody is excluded and the
     *                        message names the system as the author.
     * @param  ?int  $supervisorId  the user newly holding `supervisor_id`,
     *                              null when this write did not change it
     * @param  array<int, int>  $managerPositions  userId => 1-based "G.A. n"
     *                                             slot, for NEW attachments only
     * @param  ?int  $requestManagementRecordId  spec 0086, MT-04b: when
     *                                           $record is an Opportunity
     *                                           whose "Gestione Richieste"
     *                                           row is really one of its
     *                                           Offerte (a grid row IS a
     *                                           Quote, not the Opportunity,
     *                                           since spec 0086), the id that
     *                                           deep-link must open — never
     *                                           $record's own id. Null (every
     *                                           caller outside
     *                                           request-management) falls
     *                                           back to $record's id, the
     *                                           pre-0086 behaviour.
     */
    public function notify(
        Model $record,
        ?User $actor,
        ?int $supervisorId,
        array $managerPositions,
        ?int $requestManagementRecordId = null,
    ): void {
        // Step 1: drop the actor from both roles.
        if ($actor !== null) {
            $supervisorId = $supervisorId === $actor->id ? null : $supervisorId;
            unset($managerPositions[$actor->id]);
        }

        if ($supervisorId === null && $managerPositions === []) {
            return;
        }

        // Step 2: resolve every fact ONCE, here — a Notification presents
        // known facts and never queries, and the detail card needs relations.
        $target = RecordDetails::targetFor($record);
        $recordId = (int) $record->getKey();
        $recordLabel = (string) $record->getAttribute('name');
        $details = RecordDetails::for($record);
        $actorName = $actor?->name ?? __('The system');

        // Step 3: dispatch only once the write is durable — a notification
        // sent from inside a transaction that later rolls back would be
        // irrecoverable (same rule as NoteService::syncMentionsAndNotify()).
        DB::afterCommit(function () use ($target, $recordId, $recordLabel, $details, $actorName, $supervisorId, $managerPositions, $requestManagementRecordId): void {
            $recipients = $this->recipients($supervisorId, $managerPositions);

            if ($supervisorId !== null) {
                $recipients->get($supervisorId)?->notify(new RecordAssignmentNotification(
                    target: $target,
                    role: AssignmentRoleEnum::Supervisor,
                    recordId: $recordId,
                    recordLabel: $recordLabel,
                    position: null,
                    actorName: $actorName,
                    details: $details,
                    requestManagementRecordId: $requestManagementRecordId,
                ));
            }

            foreach ($managerPositions as $userId => $position) {
                $recipients->get($userId)?->notify(new RecordAssignmentNotification(
                    target: $target,
                    role: AssignmentRoleEnum::Manager,
                    recordId: $recordId,
                    recordLabel: $recordLabel,
                    position: $position,
                    actorName: $actorName,
                    details: $details,
                    requestManagementRecordId: $requestManagementRecordId,
                ));
            }
        });
    }

    /**
     * Every recipient of this write in ONE query, keyed by id — a deleted or
     * unknown id simply resolves to null at send time instead of throwing.
     *
     * @param  array<int, int>  $managerPositions
     * @return Collection<int, User>
     */
    private function recipients(?int $supervisorId, array $managerPositions): Collection
    {
        $ids = array_keys($managerPositions);

        if ($supervisorId !== null) {
            $ids[] = $supervisorId;
        }

        return User::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->keyBy('id');
    }
}
