/**
 * Types for the cross-domain assignment helpers (spec 0110, extended by 0113).
 * Before it can narrow the operator picker, the caller needs to know WHICH
 * product categories a selection of records requires, WHICH operational site
 * scopes it and WHICH campaigns it spans — the same question for staged import
 * rows, leads and quotes, hence a dedicated feature rather than a copy per
 * domain.
 */

/** Record families that can express an assignment scope. */
export type AssignmentDomain = 'import_rows' | 'leads' | 'quotes'

/**
 * Body of `POST /api/assignment/selection-scope`, a discriminated union on
 * `domain` so an impossible selection cannot be built.
 *
 * `import_rows` mirrors AG Grid's server-side selection state 1:1, exactly like
 * `BulkAssignImportRowPayload`: `select_all: false` — `row_ids` are the selected
 * rows; `select_all: true` — `row_ids` are the excluded ones. The other domains
 * carry the plain list of selected record ids.
 */
export type AssignmentScopePayload =
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
 * Envelope `data` of `POST /api/assignment/selection-scope`: everything the
 * client needs to scope the operator picker to the selection.
 */
export interface AssignmentScopeResult {
  /**
   * Union of the categories required by the selected records, ascending and
   * deduplicated. An EMPTY array means "no requirement" — the caller must then
   * omit `competence_category_ids` entirely instead of filtering on nothing.
   */
  product_category_ids: number[]
  /**
   * The operational site SHARED by the selection: a single distinct non-null
   * value wins, `null` otherwise (mixed sites, or even one record whose site is
   * not resolvable). `null` therefore means "do not filter the picker by site",
   * never "no site". The server always recomputes the real site from the
   * record: this value only narrows the picker.
   */
  operational_site_id: number | null
  /**
   * Distinct campaigns of the selection, ascending; always `[]` for
   * `domain: 'quotes'` (an opportunity has no campaign).
   */
  campaign_ids: number[]
}
