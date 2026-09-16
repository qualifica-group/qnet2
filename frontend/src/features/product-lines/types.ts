/**
 * Row types for the two DISTINCT `product_lines` contracts (spec 0132):
 * `ProductLineRow` for a commercial card (opportunity, request, project,
 * campaign) — root category then category, no business function on the
 * wire — and `CompetenceLineRow` for a person's competence (business
 * function then category, unchanged since spec 0111/0129). They share only
 * the read-side projections below (`ProductLine`, `ProductLineLike`,
 * `KnownProductLine`), never the edit-row shape.
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
  /**
   * The root (parentless) category the line's category hangs from (spec 0132
   * D-5), coincides with `product_category` when the category IS a root.
   * Present on every card resource (opportunity/request/project/campaign/
   * quote); absent on `EmploymentProductLine` (competences, spec 0132 D-4)
   * — its presence is what `ProductLinesReadOnlyList` uses to pick the
   * rendering format, so keep it optional rather than defaulting it.
   */
  root_category?: ProductLineRelationRef
}

/**
 * One CARD `product_lines` row as edited inline (spec 0132): the operator
 * picks the ROOT category first — `root_category_id` scopes the second
 * select, and is UI-only state that never reaches the wire, the server
 * derives and persists the effective business function from
 * `product_category_id` alone. Either id may still be null while the row is
 * being filled.
 */
export interface ProductLineRow {
  root_category_id: number | null
  product_category_id: number | null
}

/**
 * One COMPETENCE row (spec 0111 D-5, spec 0129, spec 0132 D-4): a person's
 * competence stays on the funzione-aziendale + categoria contract, a
 * DIFFERENT shape from the card's `ProductLineRow` — kept as its own type
 * rather than reused for two contracts (spec 0132 constraint).
 */
export interface CompetenceLineRow {
  business_function_id: number | null
  product_category_id: number | null
  /**
   * Spec 0129 D-3/D-5: whether the row targets "all categories of the
   * function" — `product_category_id: null` as a deliberate choice, not an
   * unfinished pick.
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
  /** See {@link ProductLine.root_category}: drives the card vs. competence rendering in `ProductLinesReadOnlyList`. */
  root_category?: ProductLineRelationRef
}

/**
 * The slice of a confirmed row `CompetenceLinesField`'s label resolution
 * actually reads (`useCompetenceLinesField`'s `indexKnownLabels`): the
 * business function only, category untouched. Lets a caller whose rows may
 * carry a null category (spec 0129, the user's competence) hand over
 * `knownLines` without inventing a placeholder category.
 */
export type KnownProductLine = Pick<ProductLine, 'business_function'>

/**
 * A fresh empty CARD row — what "Add" appends, and what both create forms
 * open on (user directive 2026-07-29): at least one row is mandatory in
 * either module, so starting from zero rows was pure friction. A factory, not
 * a shared constant: every row is mutated in place by the field editor.
 */
export function emptyProductLineRow(): ProductLineRow {
  return { root_category_id: null, product_category_id: null }
}

/** A fresh empty COMPETENCE row — mirrors {@link emptyProductLineRow} for the separate competence contract. */
export function emptyCompetenceLineRow(): CompetenceLineRow {
  return { business_function_id: null, product_category_id: null }
}
