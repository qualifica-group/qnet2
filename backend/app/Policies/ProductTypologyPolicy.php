<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `product-typologies` resource (spec 0099). No
 * special overrides: every ability maps to "product-typologies.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the ProductTypology model.
 */
class ProductTypologyPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'product-typologies';
    }
}
