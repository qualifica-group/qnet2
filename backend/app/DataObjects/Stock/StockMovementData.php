<?php

namespace App\DataObjects\Stock;

use App\Enums\StockMovementType;

/**
 * Validated payload of POST /api/stock-movements (spec 0307). `siteId` and
 * `userId` identify the source row (the destination site of a LOAD);
 * `quantity` is null only for an ADJUSTMENT, which carries `countedQuantity`.
 */
final readonly class StockMovementData
{
    public function __construct(
        public StockMovementType $type,
        public int $productId,
        public int $siteId,
        public ?int $userId,
        public ?int $toSiteId,
        public ?string $quantity,
        public ?string $countedQuantity,
        public string $movedOn,
        public ?string $note,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated StoreStockMovementRequest payload
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            type: StockMovementType::from((string) $data['type']),
            productId: (int) $data['product_id'],
            siteId: (int) $data['operational_site_id'],
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            toSiteId: isset($data['to_operational_site_id']) ? (int) $data['to_operational_site_id'] : null,
            quantity: isset($data['quantity']) ? (string) $data['quantity'] : null,
            countedQuantity: isset($data['counted_quantity']) ? (string) $data['counted_quantity'] : null,
            movedOn: (string) ($data['moved_on'] ?? now()->toDateString()),
            note: isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null,
        );
    }
}
