<?php

declare(strict_types=1);

namespace App\Services\Absences\Requests;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Notifications\AbsenceRequestNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who hears about each step of an absence request (spec 0278, notifications
 * block). The actor is never notified. Delivery is deferred until the outermost
 * transaction commits, so a rolled-back step notifies nobody. Expects the
 * relations loaded by AbsenceRequestService::RELATIONS.
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
        $notification = new AbsenceRequestNotification(
            $event,
            $request->id,
            $request->user->id,
            $request->user->name,
            $request->absenceType->name,
            $this->period($request),
            $actor->name,
            $automatic,
            $reason,
        );
        $ids = $recipients->pluck('id')->unique()->reject(fn (int $id): bool => $id === $actor->id)->values();

        // The loaded relations are partial (id, name): reload the recipients so email and locale are there.
        DB::afterCommit(fn () => User::query()->whereKey($ids)->get()->each(fn (User $user) => $user->notify($notification)));
    }

    private function period(AbsenceRequest $request): string
    {
        $from = $request->starts_on->format('Y-m-d');
        $to = $request->ends_on->format('Y-m-d');

        return $from === $to ? $from : "{$from} - {$to}";
    }
}
