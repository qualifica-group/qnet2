<?php

namespace App\Enums;

/**
 * Lifecycle of a `FieldChangeRequest` row (spec 0078, D-4/D-5). A request is
 * born `Pending`, and a manager decision moves it to a terminal state —
 * `Approved` (the value was applied to the subject) or `Rejected` (the
 * subject stays untouched). Terminal states are immutable: neither approve
 * nor reject can run twice on the same row (AC-031).
 */
enum FieldChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
