<?php

namespace App\Enums;

/**
 * Discriminates which usage context an `attribute_category` pivot row
 * belongs to (spec 0061): the same catalogue Attribute can be assigned to a
 * category's "Attributi Prodotto" section, its "Attributi Opportunita'"
 * section, or both (two separate pivot rows, one per context). Default
 * context everywhere is Opportunity — the pre-existing single-context
 * assignment model (spec 0017/0049) stays behaviorally identical.
 */
enum AttributeContext: string
{
    case Product = 'product';
    case Opportunity = 'opportunity';
}
