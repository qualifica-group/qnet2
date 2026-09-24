/**
 * Types for the generic, domain-driven DataTable framework.
 * Source of truth: docs/api/0002-generic-tables.md (frozen API contract).
 *
 * One pair of endpoints serves every domain; the `{domain}` segment selects the
 * server-side definition. These types are domain-agnostic: the frontend renders
 * whatever schema the backend returns for a given domain.
 */
import type {
  AdvancedFilterDescriptor,
  AdvancedFilterValues,
} from '@/features/table/advanced-filters/types'

/** Field type that drives the default cell rendering/formatting. */
export type ColumnType = 'text' | 'number' | 'datetime' | 'enum' | 'tags' | 'badge' | 'boolean'

/**
 * A row's identifier. Every domain but `notifications` uses a numeric,
 * auto-increment id; `notifications` uses a uuid (spec 0150 D-6) — the type is
 * widened here, at the single source of truth, rather than per-domain, so the
 * generic table framework (selection, `getRowId`, row actions) stays agnostic
 * of which shape a given domain picked.
 */
export type TableRowId = number | string

/**
 * One entry of a backend-resolved option list for an `editor: 'select'` column
 * (spec 0055 D-2). `value` is what the PATCH submits (an id for a relation-like
 * column such as the working status), `label` what the operator reads, and
 * `requires_note` whether picking it must be accompanied by a note — the server
 * enforces that rule regardless (0054 D-5), this only lets the grid ask first.
 * `color` is the same badge color TOKEN the cell renderer already maps
 * (`swatchClassFor`), so the editor marks an option with the very dot its cell
 * shows once committed; absent when the domain has no color for the option.
 */
export interface SelectOption {
  value: string | number
  label: string
  color?: string | null
  requires_note?: boolean
}

/**
 * Per-value badge metadata for a `badge` column, supplied by the backend from a
 * domain enum (EnumMeta). The frontend maps a row value to its entry to render
 * the label/color/icon — it never knows the domain enum itself.
 */
export interface EnumBadge {
  value: string
  label: string
  /** Color token (e.g. "blue", "violet"); mapped to a badge style on render. */
  color: string | null
  /** Optional icon name. */
  icon: string | null
  is_default?: boolean
  hidden_on_form?: boolean
  /**
   * Whether picking this option requires an accompanying note (spec 0054
   * D-5), e.g. a workflow status that demands an explanation. Absent/`false`
   * for every option that commits without one.
   */
  requires_note?: boolean
}

/** AG Grid filter type advertised per column in the config catalog. */
export type FilterType = 'text' | 'number' | 'date' | 'set' | 'boolean'

/** How a row action should be rendered. */
/**
 * How an action reads: a plain navigation/edit (`link`/`action`), one that
 * closes positively (`success`), one that closes negatively (`danger`). The
 * server's action catalog assigns it; `features/table/action-tone.ts` turns it
 * into the single colour used by both the grid and the record actions bars.
 */
export type ActionType = 'link' | 'action' | 'success' | 'danger'

/**
 * A single primary contact as carried by a row's `primary_contact` cell value
 * (the Users domain). Structured so the frontend renders the type icon + label
 * without knowing the contact-type domain. `icon` is a backend icon token.
 */
export interface PrimaryContact {
  type: string
  icon: string | null
  label: string
  value: string
}

/** A single column declared by the backend config endpoint. */
export interface TableColumn {
  /** Stable key = real DB column name (or derived field like "roles"). */
  id: string
  /** i18n key, e.g. "users.columns.name". */
  label: string
  type: ColumnType
  /**
   * Present and `'custom'` when the column is a universal custom field
   * (`custom.<key>`, spec 0021), or `'attribute'` when it is a Product
   * Category flexible attribute (`attr.<code>`, spec 0064, request-management
   * only, appended by the backend only when a category tab is selected). Both
   * ids are backend-driven and dynamic, so no per-id renderer can be
   * registered for them: the grid picks a generic fallback cell/filter by
   * `type`/`filterType` instead. Absent for native columns.
   */
  source?: 'custom' | 'attribute'
  /**
   * AG Grid filter type advertised per column in the config catalog. When
   * present it drives the filter component; otherwise it falls back to `type`.
   */
  filterType?: FilterType
  /** Visibility (default, or the user's saved override). */
  visible: boolean
  /**
   * Column width in px, or null when there is no explicit default (the grid
   * applies its own). User-overridable and persisted. See 0003.
   */
  width: number | null
  /**
   * Stable ordering key; columns arrive already sorted by it. User-overridable
   * and persisted. See 0003.
   */
  order: number
  /** Server-side whitelist: sort accepted only when true. */
  sortable: boolean
  /** Server-side whitelist: filter accepted only when true. */
  filterable: boolean
  /**
   * Whether the column accepts inline edits (spec 0053). Structural, not
   * user-overridable (ADR-0004): already reduced server-side for the actor —
   * `true` only when the column's catalog declares it editable AND the actor
   * clears the resource + per-field permission checks. Always present on the
   * wire; optional here (like `hasFilterValues`) so existing fixtures across
   * the app that predate this field keep compiling — absent behaves as
   * `false`.
   */
  editable?: boolean
  /**
   * Marks a column whose commit must be intercepted into a field-change-request
   * proposal instead of a direct PATCH (spec 0078 D-2): present only when the
   * actor lacks the field's own update permission but the column stays
   * `editable: true` regardless — the grid opens the cell, the CLIENT
   * intercepts the commit before it ever reaches the PATCH endpoint.
   * `resource`/`field` are the backend's own `(resource, field)` vocabulary
   * for `POST /field-change-requests` (`config/field-change-requests.php`),
   * which may differ from this column's own `id`. Absent for every column
   * that PATCHes directly, unchanged.
   */
  change_request?: { resource: string; field: string }
  /**
   * Overrides the `type`-driven cell editor lookup (spec 0054 D-7, extended by
   * 0055 D-1): `relation` is a `/for-select`-fed picker, `select` a dropdown
   * over the column's own backend-resolved `options`, `datetime` a date+time
   * picker, `multiselect` (user directive 2026-07-23) the to-many `/for-select`
   * picker whose value is the whole id collection, `product_lines` (spec 0075)
   * the {funzione aziendale, categoria prodotto} pair collection edited in the
   * same flow as the form, `date` (spec 0064) the same picker as `datetime`
   * restricted to a calendar day, for a Product Category attribute with no
   * time component. Declared per column by the backend; a column
   * without it keeps resolving its editor from `type`, unchanged.
   */
  editor?: 'relation' | 'select' | 'datetime' | 'date' | 'multiselect' | 'product_lines' | 'work_order_stage'
  /**
   * The `/for-select` resource backing a `relation` editor (spec 0054 D-1),
   * plus the OPTIONAL row-scoped narrowing of its option list (user directive
   * 2026-07-23): `scope` maps a `/for-select` param name to the id of the
   * column whose value on the EDITED ROW supplies it — e.g. `{
   * operational_site_id: 'operational_site' }` makes the operator picker offer
   * only the users of that row's own site. A scope column may hold a single
   * value (bare id or `{id, name}` projection) or a SET of ids — e.g. `{
   * competence_category_ids: 'assignment_category_ids' }` on the Lead operator
   * column (user directive 2026-09-10) — sent as a single or a repeated param
   * respectively. Absent (or a row whose scope column is empty) ⇒ the
   * unfiltered list, unchanged.
   *
   * `lockScope` (spec 0075 D-4): the scope is not a default the operator may
   * lift — the domain REFUSES what falls outside it, so the editor must not
   * offer the "show the whole catalogue" escape at all.
   */
  relation?: { resource: string; scope?: Record<string, string>; lockScope?: boolean }
  /**
   * Whether the column supports a Set Filter value list (POST /values). `false`
   * for computed/derived columns without a queryable value list (e.g. a
   * concatenated address or a nested contact) — those get a conditions-only
   * filter, never a Set tab. Absent or `true` behaves as supported (0005).
   */
  hasFilterValues?: boolean
  /**
   * Allowed values for enum/tags/badge columns (may be resolved dynamically),
   * OR — for an `editor: 'select'` column (spec 0055 D-2) — the backend's own
   * resolved option objects, whose `value` is the id the PATCH submits and
   * whose `requires_note` tells the grid when to ask for a note before
   * committing. The frontend never knows what the options MEAN: it renders
   * `label` and sends `value`.
   */
  options?: string[] | SelectOption[]
  /**
   * Per-value badge metadata for a `badge` column. Present only when the backend
   * declares the column as a badge; drives label/color/icon rendering.
   */
  badges?: EnumBadge[]
  /**
   * The snake_case domain-enum key this column maps to (e.g. `personal_data_type`
   * for the `user_type` badge column). When present, the frontend localizes the
   * badge label from its i18n resources (`enums.<enumKey>.<value>`) instead of the
   * backend-supplied `badges[].label`.
   */
  enumKey?: string
}

/**
 * One column's state sent to POST /tables/{domain}/preferences. The backend
 * diffs this against the default and stores only the deviations (0003). `width`
 * is omitted (not null) when the column has no explicit width, so it satisfies
 * the integer validation.
 */
export interface ColumnPreferenceInput {
  id: string
  visible: boolean
  width?: number
  order: number
}

/** A filter entry from the config filter catalog. */
export interface TableFilter {
  columnId: string
  type: FilterType
  /** Static or dynamically-resolved option list for set filters. */
  options?: string[]
}

/** Action catalog entry describing how to render a given action key. */
export interface TableActionDefinition {
  key: string
  /** i18n key, e.g. "actions.view". */
  label: string
  /** Icon name mapped to a Lucide component. */
  icon: string
  type: ActionType
  /** Whether the action requires UI confirmation before firing. */
  confirm: boolean
  /** The row field whose numeric value renders as a count badge on the action. */
  count_field?: string | null
}

/** A single sort directive. */
export interface TableSort {
  columnId: string
  direction: 'asc' | 'desc'
}

/** Default pagination state for the grid. */
export interface TablePagination {
  limit: number
}

/**
 * Full table schema returned by GET /tables/{domain}/columns (envelope `data`).
 * `resource` echoes the `{domain}` key.
 */
export interface TableConfig {
  resource: string
  columns: TableColumn[]
  filters: TableFilter[]
  actions: TableActionDefinition[]
  defaultSort: TableSort[]
  defaultPagination: TablePagination
  /**
   * Whether the current user has a saved layout for this table (so the UI can
   * offer "reset to default" only when there is something to reset). See 0003.
   */
  customized: boolean
  /**
   * The user's saved AG Grid filterModel for this table, replayed on mount so
   * filters survive a reload. `{}` (or absent) when none. Keys are filterable
   * column ids; the backend restricts them to the same allow-list as the rows
   * query.
   */
  filterState?: Record<string, unknown>
  /**
   * Whether the current user has saved filters for this table, so the UI can
   * offer "reset filters" only when there is something to reset. Mirrors
   * `customized` for the filter state.
   */
  filtersCustomized?: boolean
  /**
   * Real column ids spanned by the global quick-search (spec 0009). Empty (or
   * absent) ⇒ the domain has no global search, so the toolbar hides the search
   * box. The frontend builds the search placeholder from these columns' labels.
   */
  searchable?: string[]
  /**
   * Advanced filter catalog for this domain (spec 0032), ordered by `order`.
   * Empty (or absent) ⇒ the domain has no advanced filters, so the toolbar
   * hides the toggle affordance entirely.
   */
  advancedFilters?: AdvancedFilterDescriptor[]
  /**
   * The user's saved advanced filter values, replayed on mount so they survive
   * a reload — mirrors `filterState` for the AG Grid column filters. `null` (or
   * absent) when none.
   */
  appliedAdvancedFilters?: AdvancedFilterValues | null
}

/**
 * A row returned by POST /tables/{domain}/rows. Fields are schema-driven, so
 * values are loosely typed; `actions` is the per-row whitelist of allowed action
 * keys (computed server-side via the domain Policy).
 */
export interface TableRow {
  id: TableRowId
  /** Allowed action keys for THIS row. */
  actions: string[]
  /**
   * Whether THIS row can be inline-edited (spec 0053, D-4): the per-row
   * authorization result (`authorizeUpdate`), attached alongside `actions`. A
   * cell is editable only when both this and the column's own `editable` are
   * true. Always present on the wire; optional here so fixtures across the
   * app that predate this field keep compiling — absent behaves as `false`
   * (`resolveEditableColumnProps` reads it as `row.editable === true`).
   */
  editable?: boolean
  /**
   * Row-scoped options convention (spec 0054 follow-up, AC-026/027): a row
   * MAY carry `<columnId>_options` (e.g. `workflow_status_options`) — the
   * subset of that badge/enum column's catalog valid for THIS row, when the
   * valid set depends on row-level criteria (e.g. the working-state set
   * resolved per opportunity, spec 0047). Read generically off the index
   * signature below by `resolveRowScopedOptions`
   * (`components/data-table/cell-editor-registry.ts`) — no column declares
   * this as a named property here, since the key itself is per-column.
   * Absent for every column that has no row-scoped set: the editor then
   * offers the column's full catalog, unchanged.
   */
  [key: string]: unknown
}

/**
 * Row-level request/response types (SSRM payload/response, Set Filter
 * values, bulk-delete, saved filter views) live in `rows-types.ts`, split out
 * purely to keep this file under the engineering.md §6 size budget.
 * Re-exported here so every existing `from '@/features/table/types'` import
 * keeps working unchanged.
 */
export * from '@/features/table/rows-types'
