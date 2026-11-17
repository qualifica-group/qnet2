<?php

namespace App\DataObjects\Notifications;

use App\Enums\NotificationLevelEnum;

/**
 * The agnostic payload stored in the notifications `data` column and exposed by
 * the API. It is the single source of truth for the shape, used both on write
 * (GenericNotification builds it) and on read (NotificationResource normalizes
 * any stored row through it). This guarantees the frontend always receives the
 * same four keys with a valid `level`, regardless of what was persisted.
 *
 * `title`/`message`/`action_url` are nullable (null when absent) so the client
 * can apply its own fallbacks; `level` always resolves to a valid enum value.
 * `is_cc` marks a copy sent for information only (spec 0153, D-14: the
 * watchers copied on a task update request); false when absent.
 * `actor` is the person whose action produced the notification (e.g. who
 * wrote the @mention), shown next to the timestamp the way q-net did; a
 * snapshot of id and name, null when the notification has no human author.
 */
final readonly class NotificationData
{
    /**
     * @param  array{id: int, name: string}|null  $actor
     */
    public function __construct(
        public ?string $title = null,
        public ?string $message = null,
        public NotificationLevelEnum $level = NotificationLevelEnum::Info,
        public ?string $actionUrl = null,
        public bool $isCc = false,
        public ?array $actor = null,
    ) {}

    /**
     * Normalize a raw stored payload into the guaranteed shape: missing keys
     * become null and an unknown/invalid level falls back to Info.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: isset($data['title']) ? (string) $data['title'] : null,
            message: isset($data['message']) ? (string) $data['message'] : null,
            level: NotificationLevelEnum::fromValue(
                isset($data['level']) ? (string) $data['level'] : null,
            ),
            actionUrl: isset($data['action_url']) ? (string) $data['action_url'] : null,
            isCc: (bool) ($data['is_cc'] ?? false),
            actor: self::actorFrom($data['actor'] ?? null),
        );
    }

    /**
     * A stored actor survives only with both an id and a name: anything else
     * (missing, a legacy string, a partial array) reads as no actor.
     *
     * @return array{id: int, name: string}|null
     */
    private static function actorFrom(mixed $actor): ?array
    {
        if (! is_array($actor) || ! isset($actor['id'], $actor['name'])) {
            return null;
        }

        return ['id' => (int) $actor['id'], 'name' => (string) $actor['name']];
    }

    /**
     * The serialized contract sent to the client (and persisted on write).
     *
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null, is_cc: bool, actor: array{id: int, name: string}|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'level' => $this->level->value,
            'action_url' => $this->actionUrl,
            'is_cc' => $this->isCc,
            'actor' => $this->actor,
        ];
    }
}
