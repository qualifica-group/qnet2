<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

/**
 * A grid whose `attr.<code>` columns follow the categories its advanced filters
 * select (spec 0374, D-1, D-8): the category is deduced from the filter values
 * by `AttributeColumnCategories::fromFilters`, never sent by the client.
 *
 * The three entry points differ in the category and in the allow-list they
 * leave: rows and export read the category from the request and accept the
 * union (a column of another category is ignored, D-4); the config reads it
 * from the user's effective filters and lists only that category; the distinct
 * values have no filters at all and look every column up in the union.
 */
interface FilterScopedAttributeColumns extends AttributeColumnsScopable
{
    /**
     * @param  array<string, mixed>  $advancedFilters
     */
    public function scopeAttributeColumnsForRows(array $advancedFilters): void;

    /**
     * @param  array<string, mixed>  $advancedFilters
     */
    public function scopeAttributeColumnsForConfig(array $advancedFilters): void;

    public function scopeAttributeColumnsForValues(): void;
}
