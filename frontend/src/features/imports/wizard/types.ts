/**
 * Advanced lead import wizard types (spec 0033). Every shape below mirrors
 * the frozen backend `data_contract` 1:1 — no field is invented here. The
 * legacy two-phase import types (`features/imports/types.ts`, spec 0012)
 * are untouched; this module is a parallel, wizard-only contract.
 */

/** Lifecycle of a wizard import run, mirrored from the extended `App\Enums\ImportStatus`. */
export type ImportRunStatus =
  | 'analyzing'
  | 'configuring'
  | 'staging'
  | 'reviewing'
  | 'processing'
  | 'completed'
  | 'failed'

/** A column detected in the uploaded file during analysis. */
export interface DetectedColumn {
  /**
   * Deterministic, lossless mapping key (backend ColumnAnalysis::columnKeys):
   * the bare header name on first occurrence, `"{name}#{index}"` on a duplicate
   * header. `column_mapping`/`suggested_mapping` are keyed by THIS, never by
   * `name`, so two same-named columns stay independently mappable.
   */
  key: string
  name: string
  index: number
  duplicate: boolean
}

/** A field of the domain definition mappable from a file column. */
export interface ImportFieldDescriptor {
  id: string
  label: string
  required: boolean
  group: string
  type: string
}

/** A global (per-run, not per-row) configuration field of the domain definition. */
export interface ImportGlobalFieldDescriptor {
  id: string
  label: string
  required: boolean
  for_select_resource: string | null
  default: string | number | null
  /**
   * Whether the field accepts several ids at once (spec 0094 `data_contract`):
   * `product_ids` is the first such field. Optional/absent means the legacy
   * single-value behaviour (retro-compat with runs/fixtures predating the
   * delta, mirroring `ImportRunDetail.review_fields`).
   */
  multiple?: boolean
  /**
   * Id of another global field this one is scoped by (spec 0094): `product_ids`
   * depends on `campaign_id` — its picker is filtered by the chosen campaign's
   * effective product categories and stays disabled until it is set. `null`/
   * absent means no dependency, the legacy behaviour.
   */
  depends_on?: string | null
  /**
   * Id of a MAPPABLE field that, once a file column is mapped onto it, makes
   * this global field not just optional but forbidden (spec 0108 D-2):
   * `campaign_id` names `campaign_code`, because the campaign is then read
   * per row from the file. `null`/absent means the field is always run-wide.
   */
  required_unless_mapped?: string | null
}

/** Mapping target sentinel: the column is intentionally not imported. */
export const IGNORE_TARGET = '__ignore__'
/** Mapping target sentinel: the column feeds `extra_fields` under its original name. */
export const EXTRA_TARGET = '__extra__'

/**
 * Fields shared by every response that carries a run: the create (201) and
 * configure/confirm (200) responses. `GET .../{importRun}` extends this with
 * the wizard-only fields below (`ImportRunDetail`).
 */
export interface ImportRunSummary {
  id: number
  resource: string
  status: ImportRunStatus
  original_filename: string
  total_rows: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  imported_rows: number | null
  modified_rows: number
  has_error_report: boolean
  created_at: string
}

/** Response shape of `GET /imports/{domain}/{importRun}` (envelope `data.import_run`). */
export interface ImportRunDetail extends ImportRunSummary {
  error_count: number
  detected_columns: DetectedColumn[] | null
  /** Column name (the file header, as returned in `detected_columns[].name`) -> field id | sentinel. */
  column_mapping: Record<string, string> | null
  /** A `multiple` field's stored value (e.g. `product_ids`) is `number[]`; every other field is a scalar. */
  global_config: Record<string, string | number | number[] | null> | null
  dedup_strategy: string | null
  /** Auto-map suggestion, present only from `configuring` onward. */
  suggested_mapping: Record<string, string> | null
  fields: ImportFieldDescriptor[]
  global_fields: ImportGlobalFieldDescriptor[]
  dedup_modes: string[]
  /**
   * The FINAL persisted fields the review grid shows/edits (spec 0033 delta
   * D-2026-07-15-placeholder-review-fields), e.g. `first_name`/`last_name`
   * instead of the input-only `full_name`. `label` is a default-namespace
   * i18n key, mirroring `ImportFieldDescriptor.label`. Optional/possibly
   * empty for backward compatibility with runs/fixtures predating the
   * delta — callers fall back to the mapped-fields derivation.
   */
  review_fields?: Array<{ id: string; label: string }>
  /**
   * A saved mapping template whose `columns` (spec 0035) match, in exact
   * order, this run's detected columns — computed SERVER-SIDE, never by the
   * client. Optional/possibly absent for backward compatibility with
   * fixtures predating the delta, mirroring `review_fields` above.
   */
  matching_template?: {
    id: number
    name: string
    column_mapping: Record<string, string>
    dedup_strategy: string | null
  } | null
}

/** A saved column-mapping template (spec 0035), shared team-wide per domain. */
export interface ImportMappingTemplate {
  id: number
  name: string
  /** Ordered snapshot of the source run's column keys — the exact-match signature. */
  columns: string[]
  column_mapping: Record<string, string>
  dedup_strategy: string | null
  created_by: { id: number; name: string }
  created_at: string
}

/** Body of `PUT /imports/{domain}/{importRun}/configure`. */
export interface ConfigureImportPayload {
  column_mapping: Record<string, string>
  global_config: Record<string, string | number | number[] | null>
  dedup_strategy: string
}

/** Status of a single staged row, mirrored from `App\Enums\ImportRowStatus`. */
export type ImportRowStatus = 'valid' | 'warning' | 'error' | 'duplicate' | 'skipped'

/** Operator's chosen handling of a `duplicate` row (spec 0036). */
export type ImportRowResolution = 'skip' | 'create' | 'update'

/**
 * Which existing anagrafica (and, if any, lead) a `duplicate` row matched
 * against (spec 0036 `data_contract`, shape updated by spec 0041 BR-3).
 * Written server-side during staging/revision; `lead_id` is set only when
 * that anagrafica already has a lead on the run's campaign.
 */
export interface ImportRowDuplicateMeta {
  registry_id: number
  registry_name: string
  lead_id: number | null
  matched_on: string[]
}

/** A single staged row, as returned by the review SSRM datasource / row update. */
export interface ImportRunRowItem {
  id: number
  row_number: number
  status: ImportRowStatus
  is_edited: boolean
  duplicate_of_id: number | null
  /**
   * Duplicate match detail (spec 0036), present only for `duplicate` rows.
   * Optional/nullable for backward compatibility with fixtures/backends
   * predating the delta.
   */
  duplicate_meta?: ImportRowDuplicateMeta | null
  /** Chosen resolution for a `duplicate` row (spec 0036); optional/nullable, same compat rationale. */
  resolution?: ImportRowResolution | null
  /**
   * Per-row operator override (id only), applied via `PATCH .../rows/{row}`
   * with `{ operator_id }`. `null` means the row uses the run's global
   * `operator_id` default (`global_config`); it never resolves that default
   * onto this field.
   */
  operator_id: number | null
  /** Hydrated projection of `operator_id`, or `null` when unset. */
  operator: ImportRunRowOperator | null
  /**
   * Per-row operational-site override (id only), applied via
   * `PATCH .../rows/{row}` with `{ operational_site_id }`. Unlike operator,
   * the site is NEVER a global-config default — `null` simply means unset,
   * mirroring `operator_id`'s override mechanics without a fallback to resolve.
   */
  operational_site_id: number | null
  /** Hydrated projection of `operational_site_id`, or `null` when unset. */
  operational_site: ImportRunRowOperationalSite | null
  /**
   * Per-row product-of-interest override (spec 0094 D-4), applied via
   * `PATCH .../rows/{row}` with `{ product_ids }`. `null` means the row
   * inherits the run's global `product_ids` default (`global_config`); `[]`
   * is an explicit "no products on this row" — the two are distinct states,
   * mirroring `operator_id`'s null-inherits-default semantics but with an
   * empty-array state `operator_id` has no equivalent for.
   */
  product_ids: number[] | null
  /** Hydrated projection of `product_ids` (`{id,label}` per product), `[]` when unset/empty. */
  products: ImportRunRowProduct[]
  /**
   * The campaign THIS row resolved from the file's `campaign_code` (spec
   * 0108). `null` on a run whose campaign is global — there is no per-row
   * campaign then — and on a row whose code matched nothing, which is
   * exactly the case the review grid lets the operator fix.
   */
  campaign_id?: number | null
  /** Hydrated projection of `campaign_id`, `null` when unset/unmatched. */
  campaign?: ImportRunRowCampaign | null
  /**
   * Keyed by field id (mapped) or original column name (extra). Mostly
   * strings, but the geo fields (spec 0038) also carry the resolved
   * `country_id`/`state_id`/`province_id`/`city_id` as numbers (or `null`
   * when unresolved) fused in by the backend alongside the canonical names.
   */
  values: Record<string, string | number | null>
  messages: string[]
}

/** Minimal projection of the row's overridden operator, hydrated alongside `operator_id`. */
export interface ImportRunRowOperator {
  id: number
  name: string
}

/** Minimal projection of the row's overridden operational site, hydrated alongside `operational_site_id`. */
export interface ImportRunRowOperationalSite {
  id: number
  name: string
}

/** Minimal projection of the row's resolved campaign, hydrated alongside `campaign_id`. */
export interface ImportRunRowCampaign {
  id: number
  code: string
  name: string
}

/** A single hydrated product entry of `ImportRunRowItem.products`. */
export interface ImportRunRowProduct {
  id: number
  label: string
}

/** Response shape of `POST /imports/{domain}/{importRun}/rows` (envelope `data`). */
export interface ImportRunRowsPage {
  items: ImportRunRowItem[]
  pagination: { total: number; offset: number; limit: number; total_pages: number }
}

/** SSRM-style query body of `POST /imports/{domain}/{importRun}/rows`. */
export interface ImportRunRowsQuery {
  startRow: number
  endRow: number
  sortModel?: Array<{ colId: string; sort: 'asc' | 'desc' }>
  filterModel?: Record<string, unknown>
  search?: string
}

/** Counts returned alongside a row update, reflecting the run's recalculated totals. */
export interface ImportRunRowCounts {
  total: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  modified_rows: number
}

/** Response shape of `PATCH /imports/{domain}/{importRun}/rows/{row}` (envelope `data`). */
export interface ImportRunRowUpdateResult {
  row: ImportRunRowItem
  counts: ImportRunRowCounts
}

/**
 * Body of `PATCH /imports/{domain}/{importRun}/rows/assign` (combined bulk
 * assign, distinct from the single-row `PATCH .../rows/{row}`). Mirrors AG
 * Grid's own server-side selection state 1:1
 * (`gridApi.getServerSideSelectionState()`): `select_all: false` — `row_ids`
 * are the selected (included) row ids, never empty; `select_all: true` —
 * `row_ids` are the deselected (excluded) row ids. Assign-only: there is no
 * bulk clear, the single-row cell already covers that.
 *
 * `mode` (spec 0048): the shared "Assegna operatori" popup always sends it —
 * `single` (requires `operator_id`) or `balanced` (requires
 * `operational_site_id`, enumerating the Sede's operators server-side).
 * Optional for backward compat with the legacy (pre-0048) caller contract,
 * where at least one of `operator_id`/`operational_site_id` alone sufficed.
 *
 * `product_ids` (spec 0094 bulk delta): sent by the review bar's "Assegna
 * prodotti" dropdown action instead of the operator/site/mode fields —
 * assign-only, at least 1 element when present, never `null`/`[]` (those
 * bulk-clear shortcuts stay row-scoped, see
 * `UpdateImportRunRowPayload.product_ids`).
 */
export interface BulkAssignImportRowPayload {
  operator_id?: number
  operational_site_id?: number
  mode?: 'single' | 'balanced'
  product_ids?: number[]
  select_all: boolean
  row_ids: number[]
}

/**
 * Response shape of `PATCH .../rows/assign` (envelope `data`): how many rows
 * were written, plus (spec 0110, additive) how many `balanced` left without an
 * operator because no operator of the Sede is competent for them — always 0 in
 * `single` mode, and absent altogether on a response predating the delta.
 */
export interface BulkAssignImportRowResult {
  updated: number
  skipped?: number
}

/** Response shape of `GET /imports/{domain}/{importRun}/summary` (envelope `data.summary`). */
export interface ImportRunSummaryReport {
  total_rows: number
  valid_rows: number
  warning_rows: number
  error_rows: number
  duplicate_rows: number
  modified_rows: number
  mapped_fields: Array<{ column: string; field: string }>
  extra_fields: string[]
  global_config: Record<string, unknown>
  dedup_strategy: string | null
  warnings: string[]
  /**
   * Recap of how the run's `duplicate` rows were resolved (spec 0036),
   * derived server-side from their current `resolution`. Optional for
   * backward compatibility with fixtures/backends predating the delta.
   */
  duplicate_resolutions?: {
    skip: number
    create: number
    update: number
    unresolved: number
  }
  /**
   * Pre-confirm readiness of the run for the auto-convert-to-Opportunity
   * option: whether the campaign derives a product line (required by the
   * conversion action), how many rows are creatable at all, and how many
   * creatable rows still lack an operator or an operational site (both are
   * per-row-only fields, set via the review grid's per-row/bulk overrides —
   * neither has a global-config default to fall back to).
   */
  conversion_readiness: ConversionReadiness
}

/** See `ImportRunSummaryReport.conversion_readiness`. */
export interface ConversionReadiness {
  campaign_derives_product_line: boolean
  creatable_rows: number
  rows_without_operator: number
  rows_without_site: number
}

/** Body of `POST /imports/{domain}/{importRun}/confirm`. */
export interface ConfirmImportPayload {
  convert_to_opportunity: boolean
}

