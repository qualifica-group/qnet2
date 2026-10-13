<?php

declare(strict_types=1);

namespace App\DataObjects\InvoiceTags;

/**
 * Validated payload for creating an invoice tag (POST /api/invoice-tags, spec
 * 0247). `sort_order` is server-managed (placed by InvoiceTagService::create()).
 */
final readonly class CreateInvoiceTagData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: array_key_exists('description', $data) ? $data['description'] : null,
            color: (string) $data['color'],
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_active' => $this->isActive,
        ];
    }
}
