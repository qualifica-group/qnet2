<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

/**
 * A table definition that adds `attr.<code>` columns per category tab (spec
 * 0064) and so must widen its SSRM allow-lists to the UNION of every
 * category's attributes when the preferences/filter-state endpoints validate
 * a save, whichever tab produced it.
 */
interface AttributeColumnsScopable
{
    public function scopeToAllProductCategories(): void;
}
