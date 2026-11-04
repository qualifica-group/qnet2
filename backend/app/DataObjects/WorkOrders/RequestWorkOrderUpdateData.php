<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrders;

/**
 * Validated payload for POST /api/work-orders/{workOrder}/request-update
 * (spec 0352). `target` is one of the fixed groups `participants`/
 * `supervisors`/`all` (RequestWorkOrderUpdateRequest is the allow-list); the
 * recipient ids are resolved at send time off the work order's own current
 * pivots, never carried in this DTO.
 */
final readonly class RequestWorkOrderUpdateData
{
    public function __construct(
        public string $target,
        public string $message,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            target: (string) $data['target'],
            message: (string) $data['message'],
        );
    }
}
