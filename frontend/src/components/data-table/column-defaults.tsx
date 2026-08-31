/**
 * Generic, domain-agnostic column defaults for the DataTable wrapper: cell
 * renderer and value-formatter fallbacks picked from the backend column
 * schema (`type`/`source`), never from a per-id registry. Split out of
 * `data-table.tsx` (300/500-line budget) so the selection logic is a plain,
 * directly-unit-testable module.
 *
 * `source:'custom'` columns (universal custom fields, spec 0021) carry a
 * dynamic id (`custom.<key>`) that no per-id renderer map can cover — these
 * fallbacks are what make them render/format correctly out of the box.
 */
import type { ColDef, EditableCallbackParams, ICellRendererParams } from 'ag-grid-community'
import appI18n from '@/i18n'
import { resolveCellEditorSpec, type CellEditorKind } from '@/components/data-table/cell-editor-registry'
import { formatBadgeFilterValue, formatBooleanFilterValue } from '@/components/data-table/column-filters'
import { BadgeCell } from '@/features/table/cell-renderers'
import { formatDate, formatDateTimeOptionalTime } from '@/lib/formatting/date-display'
import type { TableColumn, TableRow } from '@/features/table/types'

/** A custom cell renderer keyed by column id. Receives the AG Grid cell params. */
export type CellRenderer = (params: ICellRendererParams) => React.ReactNode

/**
 * Formats a custom-field `number` column's raw value (may arrive as a numeric
 * string) with the active UI locale's separators, blank when missing/invalid.
 * Only used for `source:'custom'` columns — native `number` columns keep
 * today's plain AG Grid display untouched.
 */
function formatCustomNumber(value: unknown): string {
  const numeric =
    typeof value === 'number'
      ? value
      : typeof value === 'string' && value.trim() !== ''
        ? Number(value)
        : NaN
  return Number.isFinite(numeric)
    ? new Intl.NumberFormat(appI18n.language).format(numeric)
    : ''
}

/**
 * Whether a column is the dynamic, backend-driven kind that carries no per-id
 * renderer: `custom.<key>` (spec 0021) or `attr.<code>` (spec 0064), both
 * sharing the generic formatting fallbacks below.
 */
function isDynamicColumn(column: TableColumn): boolean {
  return column.source === 'custom' || column.source === 'attribute'
}

/**
 * Default cell value formatter for a column, when no cell renderer applies.
 * `tags` maps each raw value (a backend `code`/id) through the column's own
 * `badges`/`enumKey` metadata when present — a multiselect enum custom field
 * carries option codes, not display labels, so joining them raw would show
 * gibberish. A column with no `badges` (native
 * `tags` such as roles, already display strings; a `relation` many-to-many,
 * which the backend never resolves to a label catalog) falls through
 * unchanged — `formatBadgeFilterValue` returns the raw value verbatim when it
 * finds no match. Dynamic columns (`isDynamicColumn`) additionally get a
 * formatter for `boolean`/`number`; enum (badge) and text/relation are handled
 * by `resolveCellRenderer`/plain text respectively. Native columns of those
 * types are untouched (the `isDynamicColumn` guard).
 */
export function defaultValueFormatter(
  column: TableColumn,
  translate: (key: string) => string,
): ((value: unknown) => string) | undefined {
  if (column.type === 'tags') {
    return (value: unknown): string =>
      Array.isArray(value)
        ? value.map((item) => formatBadgeFilterValue(item, column)).join(', ')
        : String(value ?? '')
  }
  if (isDynamicColumn(column) && column.type === 'boolean') {
    return (value: unknown): string => formatBooleanFilterValue(value, translate)
  }
  if (isDynamicColumn(column) && column.type === 'number') {
    return formatCustomNumber
  }
  // Dynamic date columns carry no per-id renderer, so without this they would
  // print the raw wire value (`2026-08-03T14:30:00`) instead of the user's
  // pattern. Native date columns keep their per-id `DateCell`/`DateTimeCell`.
  // `filterType: 'date'` is how the backend marks a date-only field, which
  // must never grow a time; anything else keeps its hour when it has one.
  if (isDynamicColumn(column) && column.type === 'datetime') {
    return column.filterType === 'date' ? formatDate : formatDateTimeOptionalTime
  }
  return undefined
}

/**
 * Whether a column renders as the generic enum badge fallback: native `badge`
 * columns and dynamic `enum` columns (`source:'custom'`) share the same
 * backend-supplied badge metadata shape (`badges`/`enumKey`), so both use the
 * same agnostic `BadgeCell` — no per-id renderer needed even though the
 * dynamic column id is, well, dynamic.
 * Deliberately excludes `relation` columns: the backend emits no `badges` for
 * them (no static option catalog server-side), so they stay on the plain
 * text/`tags` fallback above regardless of source.
 */
function isEnumBadgeColumn(column: TableColumn): boolean {
  return column.type === 'badge' || (isDynamicColumn(column) && column.type === 'enum')
}

/**
 * Picks the cell renderer for a column: an explicit per-id override from the
 * caller's map, else the generic enum-badge fallback, else `undefined` (AG
 * Grid's default text cell, optionally driven by `defaultValueFormatter`).
 */
export function resolveCellRenderer(
  column: TableColumn,
  cellRenderers?: Record<string, CellRenderer>,
): CellRenderer | undefined {
  const custom = cellRenderers?.[column.id]
  if (custom) {
    return custom
  }
  return isEnumBadgeColumn(column)
    ? (params: ICellRendererParams) => (
        <BadgeCell {...params} badges={column.badges} enumKey={column.enumKey} />
      )
    : undefined
}

/**
 * Editable-related ColDef props for a column (spec 0053, extended by 0054
 * D-7): read-only unless the backend declared the column `editable` AND its
 * editor kind — `column.editor` when present (a `relation` picker overriding
 * the type-driven lookup), else `column.type` — has a registered cell editor
 * (AC-023 — an unregistered kind stays read-only rather than crashing). A
 * column declaring `editor: 'relation'`/`'multiselect'` without its
 * `relation.resource` target is malformed metadata and, defensively, also
 * stays read-only. The
 * returned callback only re-checks the PER-ROW flag (`row.editable`, D-4):
 * the column-level gate is already resolved here, once, at colDef build time.
 */
export function resolveEditableColumnProps(
  column: TableColumn,
): Pick<ColDef<TableRow>, 'editable' | 'cellEditor' | 'cellEditorParams' | 'cellEditorPopup'> {
  if (!column.editable) {
    return { editable: false }
  }
  if ((column.editor === 'relation' || column.editor === 'multiselect') && !column.relation?.resource) {
    return { editable: false }
  }
  const kind: CellEditorKind = column.editor ?? column.type
  const spec = resolveCellEditorSpec(kind)
  if (!spec) {
    return { editable: false }
  }
  return {
    editable: (params: EditableCallbackParams<TableRow>) => params.data?.editable === true,
    cellEditor: spec.cellEditor,
    cellEditorParams: spec.cellEditorParams?.(column),
    cellEditorPopup: spec.cellEditorPopup,
  }
}
