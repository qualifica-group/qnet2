<?php

declare(strict_types=1);

namespace App\DataObjects\SupportRequests;

use Illuminate\Http\UploadedFile;

/**
 * Validated payload of POST /api/support-requests (spec 0334).
 */
final readonly class SupportRequestData
{
    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function __construct(
        public int $supportTopicId,
        public string $description,
        public bool $isUrgent = false,
        public array $attachments = [],
        public ?string $pageUrl = null,
        public ?string $userAgent = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            supportTopicId: (int) $data['support_topic_id'],
            description: (string) $data['description'],
            isUrgent: (bool) ($data['is_urgent'] ?? false),
            attachments: array_values($data['attachments'] ?? []),
            pageUrl: isset($data['page_url']) ? (string) $data['page_url'] : null,
            userAgent: isset($data['user_agent']) ? (string) $data['user_agent'] : null,
        );
    }
}
