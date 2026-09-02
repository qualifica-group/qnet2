<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrders;

use App\Enums\WorkOrderType;

/**
 * Validated payload for creating a work order (POST /api/work-orders, spec
 * 0093). Declared DTO (no "magic flying array") so the StoreWorkOrderRequest
 * -> WorkOrderService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `code` is nullable here (D-1): a manual value wins, otherwise the service
 * generates the next `COM-000N` inside its transaction. `quoteLineIds`
 * defaults to `[]` (data_contract) and is validated server-side against the
 * quote's own REVENUE lines by `App\Services\WorkOrders\WorkOrderLineWriter`
 * (D-7) — never trusted as-is here.
 */
final readonly class CreateWorkOrderData
{
    /**
     * @param  array<int, int>  $quoteLineIds
     */
    public function __construct(
        public ?string $code,
        public int $quoteId,
        public string $title,
        public WorkOrderType $type,
        public ?string $callbackDate,
        public ?string $description,
        public ?string $internalNotes,
        public bool $isForceClosed,
        public ?string $forceCloseReason,
        public array $quoteLineIds,
    ) {}

    /**
     * Build from the validated StoreWorkOrderRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $code = array_key_exists('code', $data) ? trim((string) $data['code']) : null;

        return new self(
            code: ($code === null || $code === '') ? null : $code,
            quoteId: (int) $data['quote_id'],
            title: (string) $data['title'],
            type: WorkOrderType::from((string) $data['type']),
            callbackDate: array_key_exists('callback_date', $data) ? $data['callback_date'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            internalNotes: array_key_exists('internal_notes', $data) ? $data['internal_notes'] : null,
            isForceClosed: (bool) ($data['is_force_closed'] ?? false),
            forceCloseReason: array_key_exists('force_close_reason', $data) ? $data['force_close_reason'] : null,
            quoteLineIds: self::normalizeIds($data['quote_line_ids'] ?? []),
        );
    }

    /**
     * Built from GenerateContractWorkOrderRequest (spec 0095, D-6/D-11):
     * `quote_id` is the CONTRACT's own (server-derived, never the client's),
     * `code` is always generated, and the fields D-11 excludes from that
     * dialog (`callback_date`/`description`/`internal_notes`/force-close) are
     * defaulted exactly as a bare create would — the resulting work order is
     * indistinguishable from one made via POST /api/work-orders (AC-035).
     *
     * @param  array<int, int>  $quoteLineIds
     */
    public static function forContractGeneration(int $quoteId, string $title, WorkOrderType $type, array $quoteLineIds): self
    {
        return new self(
            code: null,
            quoteId: $quoteId,
            title: $title,
            type: $type,
            callbackDate: null,
            description: null,
            internalNotes: null,
            isForceClosed: false,
            forceCloseReason: null,
            quoteLineIds: self::normalizeIds($quoteLineIds),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'quote_id' => $this->quoteId,
            'title' => $this->title,
            'type' => $this->type,
            'callback_date' => $this->callbackDate,
            'description' => $this->description,
            'internal_notes' => $this->internalNotes,
            'is_force_closed' => $this->isForceClosed,
            'force_close_reason' => $this->forceCloseReason,
        ];
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, (array) $ids)));
    }
}
