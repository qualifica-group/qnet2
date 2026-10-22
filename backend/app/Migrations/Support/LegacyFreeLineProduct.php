<?php

namespace App\Migrations\Support;

use App\DataObjects\Products\CreateProductData;
use App\Enums\ProductType;
use App\Models\Product;
use App\Services\ProductService;
use RuntimeException;

/**
 * The product of a legacy offer line without a service (spec 0305, D-7). The
 * legacy sends `free_product` {key, name}, one key per line title: the
 * product is found on (old_source quotationservices, old_id key) or created
 * once under "No categoria" with the price and VAT of the line that first
 * sells it, so every line with the same title shares it.
 */
final class LegacyFreeLineProduct
{
    public const string OLD_SOURCE = 'quotationservices';

    private const int NAME_MAX_LENGTH = 191;

    public function __construct(
        private readonly ProductService $products,
        private readonly UncategorizedProductCategory $uncategorized,
    ) {}

    /**
     * @param  array<string, mixed>  $freeProduct
     * @param  array<int, string>  $warnings
     *
     * @throws RuntimeException when the legacy sent no key or no name
     */
    public function id(array $freeProduct, float $unitPrice, ?int $vatRateId, int|string $lineRef, array &$warnings): int
    {
        $key = (int) ($freeProduct['key'] ?? 0);
        $name = mb_substr(trim((string) ($freeProduct['name'] ?? '')), 0, self::NAME_MAX_LENGTH);

        if ($key <= 0 || $name === '') {
            throw new RuntimeException("Line {$lineRef}: product without a key or a name; the quote is not imported.");
        }

        $existingId = Product::query()->where('old_source', self::OLD_SOURCE)->where('old_id', $key)->value('id');

        if ($existingId !== null) {
            return $existingId;
        }

        $product = $this->products->create(new CreateProductData(
            name: $name,
            description: null,
            cost: null,
            price: $unitPrice,
            categoryId: $this->uncategorized->id(),
            productType: ProductType::default() ?? ProductType::Service,
            vatRateId: $vatRateId,
        ));

        $product->old_id = $key;
        $product->old_source = self::OLD_SOURCE;
        $product->save();

        $warnings[] = "Line {$lineRef}: product '{$name}' created from the line title.";

        return $product->id;
    }
}
