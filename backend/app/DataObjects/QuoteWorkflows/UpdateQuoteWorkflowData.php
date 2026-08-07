<?php

namespace App\DataObjects\QuoteWorkflows;

/**
 * Validated payload for a partial (PATCH) quote workflow update
 * (PUT/PATCH /api/quote-workflows/{quoteWorkflow}, spec 0047, moved onto the
 * Offerta by spec 0083 D-6). Declared DTO (no "magic flying array") so the
 * UpdateQuoteWorkflowRequest -> QuoteWorkflowService contract is explicit.
 *
 * `criteria`/`statuses`: null = NOT submitted (left untouched); array =
 * submitted, authoritative full-replace sync (mirrors
 * App\Services\OpportunityService::syncProductLines' "null = non inviato /
 * array = autoritativo" convention, spec 0040). `isActiveSubmitted` carries
 * the same not-submitted/submitted-as-false distinction plain nullable
 * booleans can't express on their own.
 */
final readonly class UpdateQuoteWorkflowData
{
    /**
     * @param  ?array<int, array{field: string, value_id: int}>  $criteria
     * @param  ?array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>  $statuses
     */
    public function __construct(
        public ?string $name = null,
        public ?bool $isActive = null,
        public bool $isActiveSubmitted = false,
        public ?array $criteria = null,
        public ?array $statuses = null,
    ) {}

    /**
     * Build from the validated UpdateQuoteWorkflowRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            isActiveSubmitted: array_key_exists('is_active', $data),
            criteria: array_key_exists('criteria', $data)
                ? CreateQuoteWorkflowData::normalizeCriteria($data['criteria'])
                : null,
            statuses: array_key_exists('statuses', $data)
                ? self::normalizeStatuses($data['statuses'])
                : null,
        );
    }

    /**
     * Only the workflow's own scalar attributes the client actually
     * submitted, ready for a partial mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->isActiveSubmitted) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }

    public function hasCriteria(): bool
    {
        return $this->criteria !== null;
    }

    public function hasStatuses(): bool
    {
        return $this->statuses !== null;
    }

    /**
     * A submitted `system_key` is deliberately DROPPED here: on update every
     * system row is identified by its persisted `id` (the writer refuses a
     * `group` change on one), and no key can be claimed or released by a
     * payload — "Validato" is a plain group, not a system key (user
     * directive 2026-08-07).
     *
     * @param  array<int, array{id?: mixed, name: mixed, description?: mixed, color?: mixed, group: mixed, requires_note?: mixed}>  $statuses
     * @return array<int, array{id: ?int, name: string, description: ?string, color: ?string, group: string, requires_note: bool}>
     */
    private static function normalizeStatuses(array $statuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'id' => isset($status['id']) ? (int) $status['id'] : null,
                'name' => (string) $status['name'],
                'description' => array_key_exists('description', $status) ? $status['description'] : null,
                'color' => array_key_exists('color', $status) ? $status['color'] : null,
                'group' => (string) $status['group'],
                'requires_note' => (bool) ($status['requires_note'] ?? false),
            ],
            $statuses,
        );
    }
}
