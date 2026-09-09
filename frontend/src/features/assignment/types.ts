/**
 * Types for the cross-domain assignment helpers (spec 0110). The competence
 * filter needs to know WHICH product categories a selection of records
 * requires before it can narrow the operator picker, and that question is the
 * same for staged import rows, leads and quotes — hence a dedicated feature
 * rather than a copy per domain.
 */

/** Record families that can express a product-category requirement. */
export type AssignmentDomain = 'import_rows' | 'leads' | 'quotes'

/**
 * Body of `POST /api/assignment/required-categories`, a discriminated union on
 * `domain` so an impossible selection cannot be built.
 *
 * `import_rows` mirrors AG Grid's server-side selection state 1:1, exactly like
 * `BulkAssignImportRowPayload`: `select_all: false` — `row_ids` are the selected
 * rows; `select_all: true` — `row_ids` are the excluded ones. The other domains
 * carry the plain list of selected record ids.
 */
export type RequiredCategoriesPayload =
  | {
      domain: 'import_rows'
      import_run_id: number
      select_all: boolean
      row_ids: number[]
    }
  | {
      domain: 'leads' | 'quotes'
      ids: number[]
    }

/**
 * Envelope `data` of `POST /api/assignment/required-categories`: the union of
 * the categories required by the selected records, ascending and deduplicated.
 * An EMPTY array means "no requirement" — the caller must then omit
 * `competence_category_ids` entirely instead of filtering on nothing.
 */
export interface RequiredCategoriesResult {
  product_category_ids: number[]
}
