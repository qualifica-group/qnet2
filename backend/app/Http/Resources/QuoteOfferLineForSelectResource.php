<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\QuoteLine;
use Illuminate\Http\Request;

/**
 * For-select projection of a QuoteLine (GET
 * /api/quote-offer-lines/for-select, spec 0093, D-8). `label` is composed
 * LIVE from the product's `code`/`name` (spec 0065 D-7: a quote line has no
 * description of its own), `sort_order` is carried alongside so the client
 * can preserve the offer's own row order (data_contract).
 *
 * @mixin QuoteLine
 */
class QuoteOfferLineForSelectResource extends ForSelectResource
{
    /** Separator between the product code and name, matching QuoteForSelectResource's own composed label. */
    private const string LABEL_SEPARATOR = ' — ';

    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'label' => $product === null ? '' : $product->code.self::LABEL_SEPARATOR.$product->name,
            'sort_order' => $this->sort_order,
        ];
    }
}
