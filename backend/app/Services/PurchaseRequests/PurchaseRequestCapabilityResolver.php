<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

use App\Enums\PurchaseRequestCapability;
use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;

/**
 * Resolves what an actor may do to the status of the lines of a request
 * (spec 0208, D-1/D-8). The matrix itself lives in PurchaseRequestLineStatus;
 * this class only maps the actor to capabilities and applies the closed-request
 * rule (no transition at all, D-8).
 */
class PurchaseRequestCapabilityResolver
{
    public const string FULFILL_PERMISSION = 'purchase-requests.fulfill';

    public const string MANAGE_PERMISSION = 'purchase-requests.manageStatuses';

    /**
     * @return array<int, PurchaseRequestCapability>
     */
    public function capabilities(User $actor, PurchaseRequest $request): array
    {
        $capabilities = [];

        // Cast both sides: some MySQL/MariaDB drivers return integer columns as strings.
        if ((int) $request->function_manager_id === (int) $actor->id) {
            $capabilities[] = PurchaseRequestCapability::Approve;
        }

        if ($actor->can(self::FULFILL_PERMISSION)) {
            $capabilities[] = PurchaseRequestCapability::Fulfill;
        }

        if ($actor->can(self::MANAGE_PERMISSION)) {
            $capabilities[] = PurchaseRequestCapability::Manage;
        }

        return $capabilities;
    }

    /**
     * The capabilities in force on the request (empty once closed, D-8/D-17).
     *
     * @return array<int, PurchaseRequestCapability>
     */
    public function effectiveCapabilities(User $actor, PurchaseRequest $request): array
    {
        return $request->isClosed() ? [] : $this->capabilities($actor, $request);
    }

    /**
     * The statuses the line may move to for $actor (empty on a closed request).
     *
     * @return array<int, PurchaseRequestLineStatus>
     */
    public function transitions(User $actor, PurchaseRequest $request, PurchaseRequestLine $line): array
    {
        if ($request->isClosed()) {
            return [];
        }

        return $line->status->allowedTransitions($this->capabilities($actor, $request));
    }
}
