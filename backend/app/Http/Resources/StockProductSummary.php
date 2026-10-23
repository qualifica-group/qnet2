<?php

namespace App\Http\Resources;

use App\Models\Product;

/**
 * The product block shared by the stock resources (spec 0307). Needs
 * `unitOfMeasure` loaded.
 */
final class StockProductSummary
{
    /**
     * @return array<string, mixed>
     */
    public static function of(Product $product): array
    {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'unit_of_measure' => [
                'id' => $product->unitOfMeasure->id,
                'code' => $product->unitOfMeasure->code,
                'name' => $product->unitOfMeasure->name,
            ],
        ];
    }
}
