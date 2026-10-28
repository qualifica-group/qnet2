<?php

namespace App\DataObjects\Products;

/**
 * One supplier row of a product write (spec 0320): the supplier registry,
 * its own price and cost and whether it is the default proposed on lines.
 */
final readonly class ProductSupplierData
{
    public function __construct(
        public int $supplierId,
        public string $price,
        public string $cost,
        public bool $isDefault,
    ) {}

    /**
     * Builds the list from the validated `suppliers` payload; with a single
     * row the default flag is forced on (D-2).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, self>
     */
    public static function listFromValidated(array $rows): array
    {
        $list = array_map(static fn (array $row): self => new self(
            supplierId: (int) $row['supplier_id'],
            price: bcadd((string) ($row['price'] ?? 0), '0', 2),
            cost: bcadd((string) ($row['cost'] ?? 0), '0', 2),
            isDefault: (bool) ($row['is_default'] ?? false),
        ), array_values($rows));

        if (count($list) === 1) {
            return [new self($list[0]->supplierId, $list[0]->price, $list[0]->cost, true)];
        }

        return $list;
    }
}
