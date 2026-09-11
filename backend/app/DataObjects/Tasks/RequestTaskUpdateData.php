<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for POST /api/tasks/{task}/request-update (spec 0118,
 * D-10..D-12). `recipientIds` is normalized to unique ints, the same
 * convention CreateTaskData::normalizeIds() applies to `assignee_ids`/
 * `watcher_ids`. `message` is optional and nullable (D-12): the field may
 * stay empty, since TaskUpdateRequested always fills in a default body.
 */
final readonly class RequestTaskUpdateData
{
    /**
     * @param  array<int, int>  $recipientIds
     */
    public function __construct(
        public array $recipientIds,
        public ?string $message = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            recipientIds: array_values(array_unique(array_map(
                static fn (mixed $id): int => (int) $id,
                (array) $data['recipient_ids'],
            ))),
            message: isset($data['message']) ? (string) $data['message'] : null,
        );
    }
}
