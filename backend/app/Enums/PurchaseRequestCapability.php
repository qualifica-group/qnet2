<?php

namespace App\Enums;

/**
 * What an actor may do to the status of a purchase request line (spec 0208,
 * D-1/D-8). Each capability unlocks a slice of the transition matrix owned by
 * PurchaseRequestLineStatus::allowedTransitions().
 */
enum PurchaseRequestCapability: string
{
    /** The assigned function manager: approves or rejects a pending line. */
    case Approve = 'approve';

    /** `purchase-requests.fulfill`: orders, receives or parks an approved line. */
    case Fulfill = 'fulfill';

    /** `purchase-requests.manageStatuses`: any state to any other state. */
    case Manage = 'manage';
}
