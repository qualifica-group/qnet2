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
 */
final readonly class NotificationData
{
    public function __construct(
        public ?string $title = null,
        public ?string $message = null,
        public NotificationLevelEnum $level = NotificationLevelEnum::Info,
        public ?string $actionUrl = null,
        public bool $isCc = false,
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
        );
    }

    /**
     * The serialized contract sent to the client (and persisted on write).
     *
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null, is_cc: bool}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'level' => $this->level->value,
            'action_url' => $this->actionUrl,
            'is_cc' => $this->isCc,
        ];
    }
}
