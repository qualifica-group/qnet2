/**
 * Types for the cross-domain assignment helpers (spec 0110, extended by 0113).
 * Before it can narrow the operator picker, the caller needs to know WHICH
 * product categories a selection of records requires, WHICH operational site
 * scopes it and WHICH campaigns it spans — the same question for staged import
 * rows, leads and quotes, hence a dedicated feature rather than a copy per
 * domain.
 */

/** Record families that can express an assignment scope. */
export type AssignmentDomain = 'import_rows' | 'leads' | 'quotes' | 'enrollees'

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
      domain: 'leads' | 'quotes' | 'enrollees'
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
   * `domain: 'quotes'`/`'enrollees'` (an opportunity has no campaign — spec
   * 0130 D-9: `enrollees` selects the same quote records, scoped Iscritti).
   */
  campaign_ids: number[]
  /**
   * Whether at least one operator is a valid choice for EVERY record in scope
   * of the selection. It is not implied by the fields above: competence is an
   * OR over the required categories, so a picker filtered on their union still
   * offers operators valid for only SOME of the records. `true` for an empty
   * selection (nothing to cover).
   */
  single_operator_available: boolean
  /**
   * The "Smistamento equo" picker's groups (spec 0168 D-1): one per Sede
   * resolved from the selection, carrying the operators competent for at
   * least one of that Sede's records (union of their per-record pools). A
   * Sede whose union is empty produces no group — its records count in
   * `balanced_unassignable_count` instead. Ordered by `operational_site_label`
   * asc, operators by `label` asc (server-side).
   */
  balanced_groups: AssignmentScopeBalancedGroup[]
  /** Records with no resolvable Sede or an empty operator pool (spec 0168 D-1/AC-003). */
  balanced_unassignable_count: number
}

/** One operator option inside a `AssignmentScopeBalancedGroup` (spec 0168). */
export interface AssignmentScopeBalancedOperator {
  id: number
  label: string
  avatar_url: string | null
  /**
   * The same initial load the balanced distribution would start from for
   * this operator (leads/import_rows: COUNT leads; quotes/enrollees: COUNT
   * quotes), 0 when the operator has none.
   */
  load: number
}

/** One Sede group of the "Smistamento equo" picker (spec 0168 D-1). */
export interface AssignmentScopeBalancedGroup {
  operational_site_id: number
  operational_site_label: string
  /** Records of this Sede with a non-empty pool — the ones this group can receive. */
  record_count: number
  operators: AssignmentScopeBalancedOperator[]
}

/**
 * One entry of `operators_by_site` (spec 0168 `data_contract`), the optional
 * `mode: 'balanced'` field of the three assignment endpoints (leads,
 * request-management/enrollee-management, import rows): restricts a Sede's
 * pool to the operators the user left selected in that group. The frontend
 * sends one entry per group with at least one operator still selected.
 */
export interface BalancedOperatorsBySiteEntry {
  operational_site_id: number
  operator_ids: number[]
}
