/**
 * Shared business-function + product-category row types (spec 0057,
 * generalized from the opportunity form's original `OpportunityProductLine`/
 * `OpportunityProductLineRow`, spec 0040 amendment rev.3). Both the
 * opportunities and request-management create forms consume the same shape.
 */

/** A hydrated `{id, name}` relation projection (business function or product category). */
export interface ProductLineRelationRef {
  id: number
  name: string
}

/** A confirmed business-function + product-category pair, as returned by the server. */
export interface ProductLine {
  id: number
  business_function: ProductLineRelationRef
  product_category: ProductLineRelationRef
}

/** One `product_lines` row as edited inline (either id may still be empty while the row is being filled). */
export interface ProductLineRow {
  business_function_id: number | null
  product_category_id: number | null
  /**
   * Spec 0129 D-3/D-5: whether the row targets "all categories of the
   * function" — `product_category_id: null` as a deliberate choice, not an
   * unfinished pick. Only meaningful in the `competence` variant of
   * `ProductLinesField`; every other caller leaves it `undefined` and the
   * checkbox is never rendered.
   */
  all_categories?: boolean
}

/**
 * The shape `ProductLinesReadOnlyList` renders: a confirmed pair whose
 * category MAY be null (spec 0129 D-3 — a user's "all categories of the
 * function" row). `ProductLine` (category always present) satisfies this
 * structurally, so every existing caller (offers, opportunities) keeps
 * passing `ProductLine[]` unchanged.
 */
export interface ProductLineLike {
  id: number
  business_function: ProductLineRelationRef
  product_category: ProductLineRelationRef | null
}

/**
 * The slice of a confirmed row `ProductLinesField`'s label resolution
 * actually reads (`useProductLinesField`'s `indexKnownLabels`): the business
 * function only, category untouched. Lets a caller whose rows may carry a
 * null category (spec 0129, the user's competence) hand over `knownLines`
 * without inventing a placeholder category.
 */
export type KnownProductLine = Pick<ProductLine, 'business_function'>

/**
 * A fresh empty row — what "Add" appends, and what both create forms open on
 * (user directive 2026-07-29): at least one row is mandatory in either module,
 * so starting from zero rows was pure friction. A factory, not a shared
 * constant: every row is mutated in place by the field editor.
 */
export function emptyProductLineRow(): ProductLineRow {
  return { business_function_id: null, product_category_id: null }
}
