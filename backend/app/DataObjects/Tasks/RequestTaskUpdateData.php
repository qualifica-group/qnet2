<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for POST /api/tasks/{task}/request-update (spec 0153,
 * D-14, superseding spec 0118 D-10..D-12's `recipient_ids` shape). `target`
 * is one of the fixed groups `assignees`/`observers`/`all`
 * (RequestTaskUpdateRequest is the allow-list); `message` is now REQUIRED
 * (3..2000 chars) — the actual recipient/CC ids are resolved at write time by
 * App\Services\Tasks\TaskActionService::requestUpdate() off the Task's OWN
 * current pivots, never carried in this DTO.
 */
final readonly class RequestTaskUpdateData
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
