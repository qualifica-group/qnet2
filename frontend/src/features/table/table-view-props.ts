import type { ICellRendererParams } from 'ag-grid-community'
import type { ReactNode } from 'react'
import type { AdvancedFilterValues } from '@/features/table/advanced-filters/types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'
import type { RowActionHandler, RowActionsOptions } from '@/features/table/row-actions'
import type { CellCommitInterceptor } from '@/features/table/use-table-cell-edit'
import type { TableConfigScope } from '@/features/table/use-table-config'
import type { TableRendererMap } from '@/features/table/renderer-registry'
import type { TableRow, TableRowsAggregates, TableRowScope } from '@/features/table/types'

/**
 * Props of the generic `TableView` (extracted from `table-view.tsx` purely to
 * keep that file under the engineering.md §6 size budget — this interface's
 * extensive JSDoc is the actual bulk).
 */
export interface TableViewProps extends RowActionsOptions {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /**
   * Narrows the domain's config/rows/Set-Filter-values to one scope (spec
   * 0064: request-management's Product Category tabs). Omitted ⇒ today's
   * unscoped behavior for every other domain. The adapter is expected to key
   * its own `<TableView>` element by the scope (e.g. by category id) so a
   * scope change remounts the whole table and restarts every client-side
   * state (search, filters, layout) from the fresh config's defaults (D-4) —
   * this component does not react to a scope prop CHANGE on its own.
   */
  scope?: TableConfigScope
  /**
   * Narrows the domain's rows/values/export requests to one PARENT RECORD
   * (spec 0067 D-1: the Opportunity detail's Quotes panel). Distinct from
   * `scope` above: `scope` selects a config SHAPE and enters the config's
   * query key; `rowScope` selects a ROW SET and never enters any query key,
   * because the config is identical scoped or not (D-1). Read once as
   * primitives, like `scope`. Omitted ⇒ today's unscoped behavior.
   */
  rowScope?: TableRowScope
  /**
   * Reports the grid's live total row count to the caller (spec 0067 D-9),
   * e.g. so a panel header can show its own counter without a separate
   * count query. Composes alongside the toolbar's own "N rows" counter — it
   * does not replace it.
   */
  onRowCountChanged?: (count: number | null) => void
  /** Per-domain custom cell renderers, keyed by column id. Optional. */
  renderers?: TableRendererMap
  /**
   * Handler invoked when a row action fires. The generic table only renders the
   * affordance (crossing `row.actions` with the catalog); the concrete behavior
   * (open sheet, run delete, …) belongs to the domain adapter.
   */
  onAction: RowActionHandler
  /**
   * Import action, threaded through to the toolbar's `importSlot` (spec 0012).
   * The adapter owns the permission gate (`<Can>`) and the dialog; TableView
   * only forwards the node.
   */
  importSlot?: ReactNode
  /**
   * Optional per-row predicate gating which rows can be checked for bulk
   * selection (spec 0048 AC-040, e.g. a Lead already assigned is not
   * selectable). Forwarded verbatim to `DataTable`; omitted, every row stays
   * selectable.
   */
  isRowSelectable?: (row: TableRow) => boolean
  /**
   * Extra bulk action(s) merged into the single "Actions" dropdown alongside
   * the built-in "delete selected" whenever the selection is non-empty (spec
   * 0048 AC-041). Receives the current selection (ids AND row data — AC-031
   * needs the latter) and returns action descriptors; the domain adapter owns
   * everything about each action (dialog, mutation, permission gate) —
   * TableView only reserves the slot and enables the checkbox column when
   * this is supplied.
   */
  getBulkActions?: (selection: TableSelection) => BulkAction[]
  /** Suppresses the generic built-in "delete selected" bulk action (spec 0156 D-6); see `useBulkActionsSlot`. */
  disableBuiltinDelete?: boolean
  /**
   * Enables AG Grid's Master/Detail (spec 0059 D-4), forwarded verbatim to
   * `DataTable`. Additive opt-in: omitted, every other domain is unaffected.
   * The pattern stays isolated to `rewarded-referents`, its one consumer.
   */
  masterDetail?: boolean
  /** The detail panel's renderer, required (by the caller) whenever `masterDetail` is true. */
  detailCellRenderer?: (params: ICellRendererParams<TableRow>) => ReactNode
  /** Detail row grows to fit its content instead of a fixed pixel height. */
  detailRowAutoHeight?: boolean
  /**
   * One-time deep-link filters (e.g. a Tasks dashboard card's
   * `?status=&assignment=`) that win over the user's persisted advanced
   * filters for THIS visit only (spec 0151 D-2): forwarded verbatim to
   * `useAdvancedFilters`'s `override`. `null`/omitted ⇒ today's behavior.
   */
  advancedFiltersOverride?: AdvancedFilterValues | null
  /**
   * Invoked once the user exercises the normal Apply/Reset flow in the
   * advanced-filters panel, so the caller can drop `advancedFiltersOverride`
   * (e.g. strip the query params from the URL) now that the persisted state
   * takes back over (spec 0151 D-2).
   */
  onAdvancedFiltersOverrideCleared?: () => void
  /**
   * Renders a footer slot below the grid from the domain's own
   * `meta.aggregates` (spec 0156 D-3), e.g. a "totale minuti stimati" figure
   * over the WHOLE filtered set (not just the current page). Called with
   * `undefined` for a domain whose `TableDefinition::aggregates()` stays the
   * default empty one — return `null` in that case. Omitted entirely, no
   * footer renders and no domain sees any change.
   */
  renderFooter?: (aggregates: TableRowsAggregates | undefined) => ReactNode
  /**
   * A generic, opt-in slot rendered directly below the grid's rows (spec 0156
   * D-7's quick-create bar): the adapter owns everything about it (fields,
   * validation, submit) — `TableView` only reserves the space and refreshes
   * nothing on its own; the adapter calls the imperative handle's `refresh()`
   * itself once its own create succeeds. Omitted entirely, no domain sees any
   * change.
   */
  pinnedRowSlot?: ReactNode
  /** Per-domain cell-commit interception (spec 0156 D-8), forwarded verbatim to `DataTable`. */
  interceptCellCommit?: CellCommitInterceptor
  /**
   * Server-side tree data (spec 0157 D-1), forwarded verbatim to `DataTable`
   * and to the SSRM datasource. Off by default; a no-op for every domain but
   * `tasks`'s "Sintetica" view.
   */
  treeData?: boolean
}
