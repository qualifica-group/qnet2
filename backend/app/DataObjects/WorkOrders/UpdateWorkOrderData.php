<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrders;

use App\Enums\WorkOrderType;

/**
 * Validated payload for a partial (PATCH) work order update
 * (PUT/PATCH /api/work-orders/{workOrder}, spec 0093). `code`/`quote_id` are
 * GONE — immutable after create (D-1/D-5, rejected at the FormRequest layer
 * via `prohibited`, never reach this DTO).
 *
 * Nullable columns (`callback_date`/`description`/`internal_notes`/
 * `force_close_reason`) each carry a `*Submitted` flag so "not submitted"
 * (leave as-is) is distinguishable from "submitted as null" (clear it) —
 * mirrors UpdateUnitOfMeasureData. `quoteLineIds` is null when the key was
 * not submitted at all (leave the pivot untouched, AC-026) or an array
 * (possibly empty) when it was — a full-replace sync (AC-025).
 */
final readonly class UpdateWorkOrderData
{
    public function __construct(
        public ?string $title = null,
        public ?WorkOrderType $type = null,
        public ?string $callbackDate = null,
        public bool $callbackDateSubmitted = false,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?string $internalNotes = null,
        public bool $internalNotesSubmitted = false,
        public ?bool $isForceClosed = null,
        public ?string $forceCloseReason = null,
        public bool $forceCloseReasonSubmitted = false,
        /** @var array<int, int>|null */
        public ?array $quoteLineIds = null,
    ) {}

    /**
     * Build from the validated UpdateWorkOrderRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: array_key_exists('title', $data) ? (string) $data['title'] : null,
            type: array_key_exists('type', $data) ? WorkOrderType::from((string) $data['type']) : null,
            callbackDate: array_key_exists('callback_date', $data) ? $data['callback_date'] : null,
            callbackDateSubmitted: array_key_exists('callback_date', $data),
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            internalNotes: array_key_exists('internal_notes', $data) ? $data['internal_notes'] : null,
            internalNotesSubmitted: array_key_exists('internal_notes', $data),
            isForceClosed: array_key_exists('is_force_closed', $data) ? (bool) $data['is_force_closed'] : null,
            forceCloseReason: array_key_exists('force_close_reason', $data) ? $data['force_close_reason'] : null,
            forceCloseReasonSubmitted: array_key_exists('force_close_reason', $data),
            quoteLineIds: array_key_exists('quote_line_ids', $data) ? self::normalizeIds($data['quote_line_ids']) : null,
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update. `is_force_closed`/`force_close_reason`'s D-4
     * pairing (a false transition zeroes the reason) is enforced by
     * WorkOrderService AFTER fill(), against the model's final state — not
     * here, since a partial PATCH may submit only one of the two.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->title !== null) {
            $attributes['title'] = $this->title;
        }

        if ($this->type !== null) {
            $attributes['type'] = $this->type;
        }

        if ($this->callbackDateSubmitted) {
            $attributes['callback_date'] = $this->callbackDate;
        }

        if ($this->descriptionSubmitted) {
            $attributes['description'] = $this->description;
        }

        if ($this->internalNotesSubmitted) {
            $attributes['internal_notes'] = $this->internalNotes;
        }

        if ($this->isForceClosed !== null) {
            $attributes['is_force_closed'] = $this->isForceClosed;
        }

        if ($this->forceCloseReasonSubmitted) {
            $attributes['force_close_reason'] = $this->forceCloseReason;
        }

        return $attributes;
    }

    public function hasQuoteLineIds(): bool
    {
        return $this->quoteLineIds !== null;
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, (array) $ids)));
    }
}
