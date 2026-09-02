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
 *
 * `attributeValues` (spec 0098, D-1/D-5): the dynamic "Informazioni
 * aggiuntive" map, `null` when the key was absent. Out of attributes() like
 * every collection above — the column is not fillable and the map is
 * validated/merged by WorkOrderAttributeValueWriter AFTER the quote lines
 * are synced (D-6), i.e. against the applicable set those lines' categories
 * produce (the same set the form rendered its fields from).
 */
final readonly class CreateWorkOrderData
{
    /**
     * @param  array<int, int>  $quoteLineIds
     * @param  array<int, int>  $supervisorIds
     * @param  array<int, int|null>  $participantSlots
     * @param  array<string, mixed>|null  $attributeValues
     */
    public function __construct(
        public ?string $code,
        public int $quoteId,
        public string $title,
        public WorkOrderType $type,
        public string $startDate,
        public ?string $callbackDate,
        public ?string $description,
        public ?string $internalNotes,
        public bool $isForceClosed,
        public ?string $forceCloseReason,
        public array $quoteLineIds,
        public array $supervisorIds,
        public array $participantSlots,
        public ?array $attributeValues = null,
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
            startDate: (string) $data['start_date'],
            callbackDate: array_key_exists('callback_date', $data) ? $data['callback_date'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            internalNotes: array_key_exists('internal_notes', $data) ? $data['internal_notes'] : null,
            isForceClosed: (bool) ($data['is_force_closed'] ?? false),
            forceCloseReason: array_key_exists('force_close_reason', $data) ? $data['force_close_reason'] : null,
            quoteLineIds: self::normalizeIds($data['quote_line_ids'] ?? []),
            supervisorIds: self::normalizeIds($data['supervisor_ids'] ?? []),
            participantSlots: self::normalizeSlots($data['participant_slots'] ?? []),
            attributeValues: array_key_exists('attribute_values', $data)
                ? (array) $data['attribute_values']
                : null,
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
     * `startDate`/`supervisorIds` ARE part of that dialog (spec 0096 D-5):
     * neither may be empty on a commessa, and D-11's own rule is "the
     * generation form carries exactly the fields that cannot be left empty".
     * The team (`participantSlots`) deliberately stays empty here — it is
     * assigned later from the work order's own form.
     *
     * @param  array<int, int>  $quoteLineIds
     * @param  array<int, int>  $supervisorIds
     */
    public static function forContractGeneration(
        int $quoteId,
        string $title,
        WorkOrderType $type,
        string $startDate,
        array $supervisorIds,
        array $quoteLineIds,
    ): self {
        return new self(
            code: null,
            quoteId: $quoteId,
            title: $title,
            type: $type,
            startDate: $startDate,
            callbackDate: null,
            description: null,
            internalNotes: null,
            isForceClosed: false,
            forceCloseReason: null,
            quoteLineIds: self::normalizeIds($quoteLineIds),
            supervisorIds: self::normalizeIds($supervisorIds),
            participantSlots: [],
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
            'start_date' => $this->startDate,
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

    /**
     * The ordered, gap-aware `participant_slots` payload (spec 0096 D-3), kept
     * POSITIONAL: nulls are empty slots and must survive, so this normalizes
     * element types WITHOUT compacting — the exact opposite of normalizeIds().
     *
     * @return array<int, int|null>
     */
    private static function normalizeSlots(mixed $slots): array
    {
        return array_values(array_map(
            static fn (mixed $id): ?int => $id === null ? null : (int) $id,
            (array) $slots,
        ));
    }
}
