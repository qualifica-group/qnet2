<?php

declare(strict_types=1);

namespace App\DataObjects\DocumentLayouts;

/**
 * Validated payload for cloning a document layout (POST
 * /api/document-layouts/{documentLayout}/clone, spec 0339): the copy's own
 * identity. Everything else is read from the source layout (D-2).
 */
final readonly class CloneDocumentLayoutData
{
    public function __construct(
        public string $name,
        public string $code,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(name: (string) $data['name'], code: (string) $data['code']);
    }
}
