<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts\Rendering\Blocks;

use App\Models\QuoteLine;
use App\Services\DocumentLayouts\Rendering\ValueFormatter;

/**
 * Resolves ONE of the products_table's 9 closed ColumnKey values (spec 0069
 * config_schema #4) to display text for a single QuoteLine — shared by
 * ProductsTableRenderer for every `lines[].keys` entry. `discount` is
 * deliberately absent (0069 D-4): there is no such column in this allow-list.
 */
final class ProductLineColumnResolver
{
    public function value(string $key, QuoteLine $line): string
    {
        return match ($key) {
            'code' => ValueFormatter::text($line->product?->code),
            'name' => ValueFormatter::text($line->product?->name),
            'description' => ValueFormatter::text($line->product?->description),
            'quantity' => ValueFormatter::quantity($line->quantity),
            'unit_price' => ValueFormatter::currency($line->unit_price),
            'vat_rate' => ValueFormatter::vatRate($line->vatRate),
            'net_amount' => ValueFormatter::currency($line->net_amount),
            'vat_amount' => ValueFormatter::currency($line->vat_amount),
            'total_amount' => ValueFormatter::currency($line->total_amount),
            default => '',
        };
    }
}
