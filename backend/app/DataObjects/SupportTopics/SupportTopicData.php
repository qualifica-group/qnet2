<?php

declare(strict_types=1);

namespace App\DataObjects\SupportTopics;

use Illuminate\Support\Arr;

/**
 * Validated payload for creating (POST) or partially updating (PATCH) a
 * support topic (spec 0334). `attributes` holds only the SUBMITTED columns,
 * so a null value is a legitimate "clear this FK"; `assigneeIds`/`watcherIds`
 * are null when the key was not submitted (leave the pivot untouched).
 */
final readonly class SupportTopicData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $assigneeIds
     * @param  array<int, int>|null  $watcherIds
     */
    public function __construct(
        public array $attributes,
        public ?array $assigneeIds,
        public ?array $watcherIds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            attributes: Arr::except($data, ['assignee_ids', 'watcher_ids']),
            assigneeIds: array_key_exists('assignee_ids', $data) ? self::normalizeIds($data['assignee_ids']) : null,
            watcherIds: array_key_exists('watcher_ids', $data) ? self::normalizeIds($data['watcher_ids'] ?? []) : null,
        );
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, (array) $ids)));
    }
}
