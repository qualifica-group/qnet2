<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use App\Enums\AttributeContext;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Model;

/**
 * Where a grid's `attr.<code>` values live and which attribute set they
 * follow (spec 0350, D-7): the JSON column the handlers read/sort/filter
 * through, the `AttributeContext` the set is resolved in, and the model whose
 * field-permission catalogue gates the cell edit (`attribute_values`).
 *
 * `$valuesColumn` is always a server constant, never request input
 * (backend.md §8).
 */
final readonly class AttributeGridTarget
{
    /**
     * @param  class-string<Model>  $gateModel
     */
    public function __construct(
        public string $valuesColumn,
        public AttributeContext $context,
        public string $gateModel,
    ) {}

    /** Gestione Richieste, Offerte (and Contratti, which read their offer's). */
    public static function quote(string $valuesColumn = 'quotes.attribute_values'): self
    {
        return new self($valuesColumn, AttributeContext::Quote, Quote::class);
    }

    public static function workOrder(): self
    {
        return new self('work_orders.attribute_values', AttributeContext::WorkOrder, WorkOrder::class);
    }
}
