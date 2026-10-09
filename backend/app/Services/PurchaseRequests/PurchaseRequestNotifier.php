<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmittedNotification;
use Illuminate\Validation\ValidationException;

/**
 * Tells the function manager about a purchase request (spec 0208, D-2): on
 * creation (unless the author is the manager) and on demand through the
 * "Invia al responsabile" button.
 */
class PurchaseRequestNotifier
{
    /** Notify on creation; the author never notifies themselves. */
    public function created(PurchaseRequest $purchaseRequest, User $actor): void
    {
        if ($purchaseRequest->function_manager_id === $actor->id) {
            return;
        }

        $this->send($purchaseRequest, $actor, User::query()->findOrFail($purchaseRequest->function_manager_id));
    }

    /**
     * Re-send on request. The manager must be reachable by mail: a 422 on
     * `function_manager_id` otherwise. Returns the notified manager.
     */
    public function resend(PurchaseRequest $purchaseRequest, User $actor): User
    {
        $manager = User::query()->findOrFail($purchaseRequest->function_manager_id);

        if (blank($manager->email)) {
            throw ValidationException::withMessages(['function_manager_id' => ['The function manager has no email address.']]);
        }

        $this->send($purchaseRequest, $actor, $manager);

        return $manager;
    }

    private function send(PurchaseRequest $purchaseRequest, User $actor, User $manager): void
    {
        $manager->notify(new PurchaseRequestSubmittedNotification(
            $purchaseRequest->id,
            $purchaseRequest->subject,
            $actor->name,
            [
                'Subject' => $purchaseRequest->subject,
                'Priority' => $purchaseRequest->priority->label(),
                'Requested on' => $purchaseRequest->requested_at->toDateString(),
                'Total' => (string) $purchaseRequest->grand_total,
            ],
        ));
    }
}
