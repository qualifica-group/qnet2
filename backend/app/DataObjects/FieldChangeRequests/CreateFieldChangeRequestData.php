<?php

declare(strict_types=1);

namespace App\DataObjects\FieldChangeRequests;

/**
 * Typed payload for `FieldChangeRequestCreator::handle()` (spec 0078),
 * carrying only what the client is allowed to influence: `resource`,
 * `subject_id`, `field` and `requested_value` name WHAT is being proposed,
 * `reason` is the requester's own free-text motivation. Everything else the
 * row eventually stores (current_value/labels/status/requester) is computed
 * server-side (D-6) and never crosses this boundary.
 */
final readonly class CreateFieldChangeRequestData
{
    public function __construct(
        public string $resource,
        public int $subjectId,
        public string $field,
        public mixed $requestedValue,
        public ?string $reason,
    ) {}
}
