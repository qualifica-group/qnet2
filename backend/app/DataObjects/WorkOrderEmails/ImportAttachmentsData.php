<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrderEmails;

/**
 * Validated payload for POST
 * /api/work-orders/{workOrder}/emails/{email}/attachments/import (spec 0175,
 * D-7b/c/d): declared DTO (no "magic flying array") — see
 * standards/architecture.md -> Data Transfer Objects.
 */
final readonly class ImportAttachmentsData
{
    /**
     * @param  array<int, int>  $attachmentIds
     */
    public function __construct(
        public string $source,
        public array $attachmentIds = [],
        public ?int $documentBundleId = null,
        public ?int $layoutId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            source: (string) $data['source'],
            attachmentIds: array_map(static fn ($id): int => (int) $id, (array) ($data['attachment_ids'] ?? [])),
            documentBundleId: isset($data['document_bundle_id']) ? (int) $data['document_bundle_id'] : null,
            layoutId: isset($data['layout_id']) ? (int) $data['layout_id'] : null,
        );
    }
}
