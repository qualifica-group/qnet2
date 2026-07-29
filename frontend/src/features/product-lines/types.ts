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
}

/**
 * A fresh empty row — what "Add" appends, and what both create forms open on
 * (user directive 2026-07-29): at least one row is mandatory in either module,
 * so starting from zero rows was pure friction. A factory, not a shared
 * constant: every row is mutated in place by the field editor.
 */
export function emptyProductLineRow(): ProductLineRow {
  return { business_function_id: null, product_category_id: null }
}
