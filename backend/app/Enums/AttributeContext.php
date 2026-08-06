<?php

namespace App\Enums;

/**
 * Discriminates which usage context an `attribute_category` pivot row
 * belongs to (spec 0061): the same catalogue Attribute can be assigned to a
 * category's "Attributi Prodotto" section, its "Attributi Offerta" section,
 * or both (two separate pivot rows, one per context). There is no default —
 * every reader and writer names its context explicitly.
 *
 * The former `Opportunity` case is GONE: spec 0084 (D-1) moved the dynamic
 * "Informazioni aggiuntive" from the Opportunity to the Offerta, leaving that
 * context configurable but unread. Do not reintroduce it — an Opportunity's
 * applicable set is resolved from its Offerte's product categories.
 */
enum AttributeContext: string
{
    case Product = 'product';
    // Spec 0084: le "Informazioni aggiuntive" appartengono al singolo
    // preventivo, non all'opportunita'. Il set applicabile si risolve dalle
    // categorie dei prodotti delle righe OFFERTA (D-5), non dalle product line
    // dell'opportunita' padre.
    case Quote = 'quote';

    /**
     * The `product_categories` column carrying the inheritance barrier for
     * THIS context: each context opts in or out of its ancestors' assignments
     * independently of the other.
     */
    public function inheritanceColumn(): string
    {
        return match ($this) {
            self::Product => 'inherits_product_attributes',
            self::Quote => 'inherits_quote_attributes',
        };
    }
}
