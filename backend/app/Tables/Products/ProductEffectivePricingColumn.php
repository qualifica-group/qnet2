<?php

namespace App\Tables\Products;

use App\Models\Product;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The `price` and `cost` columns of the products grid (spec 0320, D-4/D-18):
 * the EFFECTIVE value, i.e. the default supplier's own price/cost once the
 * product has suppliers, else the standard one (`products.price`/`products.cost`)
 * — the same value the product detail shows. One instance per field
 * (`forPrice()` / `forCost()`): the field name only ever comes from those two
 * named constructors, never from user input, so the SQL below is a constant.
 * Loaded with the rows as a correlated subquery, sorted on that alias, and
 * filtered through the engine's own FilterApplier on a derived table, so the
 * filter shapes stay the generic ones.
 */
final class ProductEffectivePricingColumn
{
    public const string PRICE = 'price';

    public const string COST = 'cost';

    private function __construct(
        private readonly FilterApplier $filters,
        private readonly string $field,
    ) {}

    public static function forPrice(FilterApplier $filters): self
    {
        return new self($filters, self::PRICE);
    }

    public static function forCost(FilterApplier $filters): self
    {
        return new self($filters, self::COST);
    }

    /** The grid column id this instance serves. */
    public function columnId(): string
    {
        return $this->field;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function withEffectiveValue(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('products.*');
        }

        $query->selectRaw($this->expression().' as '.$this->alias());
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->alias(), $direction);
    }

    /**
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $columnConfig, array $filter): void
    {
        $table = 'effective_'.$this->field.'_values';

        $values = Product::query()
            ->fromSub(Product::query()->select('products.id')->selectRaw($this->expression().' as '.$this->alias()), $table)
            ->select($table.'.id');

        $this->filters->apply($values, $table.'.'.$this->alias(), $columnConfig, $filter);

        $query->whereIn('products.id', static fn (QueryBuilder $sub) => $sub->fromSub($values->toBase(), 'matching_values')->select('id'));
    }

    public function value(Product $product): ?float
    {
        $value = $product->getAttribute($this->alias());

        return $value === null ? null : (float) $value;
    }

    private function alias(): string
    {
        return 'effective_'.$this->field;
    }

    private function expression(): string
    {
        return "COALESCE((SELECT product_suppliers.{$this->field} FROM product_suppliers"
            .' WHERE product_suppliers.product_id = products.id AND product_suppliers.is_default = 1 LIMIT 1), '
            ."products.{$this->field})";
    }
}
