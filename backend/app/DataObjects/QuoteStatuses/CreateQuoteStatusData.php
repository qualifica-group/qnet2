<?php

namespace App\DataObjects\QuoteStatuses;

/**
 * Validated payload for creating a quote status (POST /api/quote-statuses,
 * spec 0065): a plain clone of CreateOpportunityStatusData.
 *
 * `sort_order` is GONE from this DTO — server-managed, placed by
 * App\Services\Statuses\StatusOrderManager::placeNew() inside
 * QuoteStatusService::create(), never accepted from the client. `group`
 * (App\Enums\QuoteStatusGroup) is REQUIRED — every row, system or custom, carries
 * a classification.
 */
final readonly class CreateQuoteStatusData
{
    public function __construct(
        public string $name,
        public ?string $color,
        public string $group,
    ) {}

    /**
     * Build from the validated StoreQuoteStatusRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            color: array_key_exists('color', $data) ? $data['color'] : null,
            group: (string) $data['group'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'group' => $this->group,
        ];
    }
}
