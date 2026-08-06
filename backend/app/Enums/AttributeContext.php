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
            self::Opportunity => 'inherits_opportunity_attributes',
            self::Quote => 'inherits_quote_attributes',
        };
    }
}
