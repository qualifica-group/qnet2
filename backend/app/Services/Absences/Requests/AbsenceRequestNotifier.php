<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Who hears about each step of an absence request (spec 0278, notifications
 * block). The actor is never notified. The HR office (active holders of
 * `absence-requests.manageAll`) gets a copy of every step, without deciding.
 * Delivery is deferred until the outermost transaction commits, so a
 * rolled-back step notifies nobody. Expects the relations loaded by
 * AbsenceRequestService::RELATIONS.
 */
final class AbsenceRequestNotifier
{
    /** Filed: approvers when it waits for a decision; person and approvers when auto-approved (D-8). */
    public function submitted(AbsenceRequest $request, User $actor): void
    {
        if ($request->status === AbsenceRequestStatus::Approved) {
            $this->dispatch($request, $actor, AbsenceRequestNotification::EVENT_APPROVED, $this->personAndApprovers($request), automatic: true);

            return;
        }

        $this->dispatch($request, $actor, AbsenceRequestNotification::EVENT_SUBMITTED, $request->approvers);
    }

    public function approved(AbsenceRequest $request, User $actor): void
    {
        $this->dispatch($request, $actor, AbsenceRequestNotification::EVENT_APPROVED, collect([$request->user]));
    }

    public function rejected(AbsenceRequest $request, User $actor): void
    {
        $this->dispatch($request, $actor, AbsenceRequestNotification::EVENT_REJECTED, collect([$request->user]), reason: $request->rejection_reason);
    }

    /** The person hears when someone else cancels; approvers when the request had been filed. */
    public function cancelled(AbsenceRequest $request, User $actor, AbsenceRequestStatus $previousStatus): void
    {
        $recipients = collect([$request->user]);

        if (in_array($previousStatus, [AbsenceRequestStatus::Submitted, AbsenceRequestStatus::Approved], true)) {
            $recipients = $recipients->merge($request->approvers);
        }

        $this->dispatch($request, $actor, AbsenceRequestNotification::EVENT_CANCELLED, $recipients);
    }

    /**
     * @return Collection<int, User>
     */
    private function personAndApprovers(AbsenceRequest $request): Collection
    {
        return collect([$request->user])->merge($request->approvers);
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    private function dispatch(AbsenceRequest $request, User $actor, string $event, Collection $recipients, bool $automatic = false, ?string $reason = null): void
    {
        // Step 1: the recipients of the contract, then the HR office in copy (once, never the actor or the person)
        $ids = $recipients->pluck('id')->unique()->reject(fn (int $id): bool => $id === $actor->id)->values();
        $copyIds = $this->hrOfficeIds()
            ->diff($ids)
            ->reject(fn (int $id): bool => in_array($id, [$actor->id, $request->user->id], true))
            ->values();

        // Step 2: one sentence per audience, delivered after the outermost commit
        $notification = fn (bool $copy): AbsenceRequestNotification => new AbsenceRequestNotification(
            $event,
            $request->id,
            $request->user->id,
            $request->user->name,
            $request->absenceType->name,
            $this->period($request),
            $actor->name,
            $automatic,
            $reason,
            $copy,
        );

        DB::afterCommit(function () use ($ids, $copyIds, $notification): void {
            $this->notify($ids, $notification(false));
            $this->notify($copyIds, $notification(true));
        });
    }

    /**
     * Holders of the stored permission only: a super-admin passes `Gate::before`
     * without being part of the HR office.
     *
     * @return Collection<int, int>
     */
    private function hrOfficeIds(): Collection
    {
        try {
            return User::permission(AbsenceApproverResolver::MANAGE_ALL_PERMISSION)->where('users.is_active', true)->pluck('users.id');
        } catch (PermissionDoesNotExist) {
            return collect();
        }
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function notify(Collection $ids, AbsenceRequestNotification $notification): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        // The loaded relations are partial (id, name): reload the recipients so email and locale are there.
        User::query()->whereKey($ids)->get()->each(fn (User $user) => $user->notify($notification));
    }

    private function period(AbsenceRequest $request): string
    {
        $from = $request->starts_on->format('Y-m-d');
        $to = $request->ends_on->format('Y-m-d');

        return $from === $to ? $from : "{$from} - {$to}";
    }
}
