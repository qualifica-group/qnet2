/**
 * Per-`ColumnType` inline cell editor (spec 0053), extended with a `relation`
 * kind orthogonal to `type` (spec 0054 D-7): the seam mapping a backend
 * column to the AG Grid editor it commits through. Same OCP pattern as
 * `CUSTOM_FIELD_COMPONENT_REGISTRY` and `ADVANCED_FILTER_FIELD_REGISTRY` —
 * adding a new kind means one new entry here, nothing else. Every built-in
 * editor listed ships with `AllEnterpriseModule` (already registered,
 * `ag-grid-setup.ts`); `relation` is this repo's own component — no new
 * dependency either way.
 */
import type { ComponentType } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import type { RichCellEditorValuesCallbackParams } from 'ag-grid-community'
import { enumLabelOf } from '@/features/config/enum-label'
import { DateTimeCellEditor } from '@/components/data-table/datetime-cell-editor'
import { MultiSelectCellEditor } from '@/components/data-table/multi-select-cell-editor'
import { OfferLinesCellEditor } from '@/features/request-management/offer-lines-cell-editor'
import { ProductLinesCellEditor } from '@/features/product-lines/product-lines-cell-editor'
import { RelationCellEditor } from '@/components/data-table/relation-cell-editor'
import { SelectCellEditor } from '@/components/data-table/select-cell-editor'
import { scalarColumnOptions, selectColumnOptions } from '@/features/table/column-options'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import type { ColumnType, TableColumn, TableRow } from '@/features/table/types'

/** The lookup key: a column's declared `editor` when present, else its `type` (spec 0054 D-1, 0055 D-1). */
export type CellEditorKind =
  | ColumnType
  | 'relation'
  | 'select'
  | 'multiselect'
  | 'date'
  | 'product_lines'
  | 'offer_lines'

/** cellEditor (a built-in name, or a custom React component) + optional per-column params, resolved once per colDef. */
export interface CellEditorSpec {
  cellEditor: string | ComponentType<CustomCellEditorProps>
  cellEditorParams?: (column: TableColumn) => Record<string, unknown>
  /** Renders the editor in AG Grid's popup layer instead of clipped to the cell (spec 0054 D-7). */
  cellEditorPopup?: boolean
}

/** Localizes an enum/badge/tags option value the same way `BadgeCell` does. */
function formatOptionLabel(column: TableColumn, value: unknown): string {
  const raw = String(value)
  if (column.enumKey) {
    return enumLabelOf(column.enumKey, raw)
  }
  return column.badges?.find((badge) => badge.value === raw)?.label ?? raw
}

/**
 * Row-scoped allow-list for a badge/enum column (spec 0054 follow-up,
 * AC-026/027): when the editing row carries `<column.id>_options` (an array
 * of values valid for THAT row only — e.g. `workflow_status_options` on
 * `request-management`, since the valid working-state set is resolved per
 * opportunity, spec 0047), the rich-select editor offers just those instead
 * of the column's full catalog. Driven entirely by the row's own shape, never
 * by column id: a row without the field (every other domain, and every other
 * badge/enum column today) keeps offering the full catalog, unchanged. The
 * server's 422 stays the actual safety net regardless — this only keeps the
 * operator from picking something it would reject.
 */
function resolveRowScopedOptions(column: TableColumn, row: TableRow | undefined): string[] {
  const catalog = scalarColumnOptions(column)
  const rowOptions = row?.[`${column.id}_options`]
  if (!Array.isArray(rowOptions)) {
    return catalog
  }
  const allowed = new Set(rowOptions.map(String))
  return catalog.filter((option) => allowed.has(option))
}

/** `agRichSelectCellEditor` params shared by enum/badge/tags: the column's own option list, row-scoped when the row provides one. */
function richSelectParams(column: TableColumn, multiSelect: boolean): Record<string, unknown> {
  return {
    values: (params: RichCellEditorValuesCallbackParams<TableRow, string>) =>
      resolveRowScopedOptions(column, params.data),
    multiSelect,
    formatValue: (value: unknown) => formatOptionLabel(column, value),
  }
}

export const CELL_EDITOR_REGISTRY: Record<CellEditorKind, CellEditorSpec> = {
  text: { cellEditor: 'agTextCellEditor' },
  number: { cellEditor: 'agNumberCellEditor' },
  // AG Grid ships no datetime-local editor (spec 0055 D-4): this repo's own
  // popup picker, wired on the generic `datetime` kind so every domain gets
  // it instead of retyping the raw `YYYY-MM-DDTHH:mm` string.
  datetime: {
    cellEditor: DateTimeCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorPopup: true,
  },
  // Spec 0064: a Product Category attribute of type `date` (no time
  // component). Same popup picker as `datetime`, told to render `type="date"`
  // and emit `YYYY-MM-DD` via the `dateOnly` param.
  date: {
    cellEditor: DateTimeCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorParams: () => ({ dateOnly: true }),
    cellEditorPopup: true,
  },
  boolean: { cellEditor: 'agCheckboxCellEditor' },
  enum: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, false) },
  badge: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, false) },
  tags: { cellEditor: 'agRichSelectCellEditor', cellEditorParams: (column) => richSelectParams(column, true) },
  // Spec 0055 D-2: a dropdown over the column's OWN backend-resolved options
  // (objects carrying `value`/`label`/`requires_note`), narrowed per row by
  // the editor itself. Distinct from `enum`/`badge`, whose options are plain
  // scalars rendered by AG Grid's built-in rich select.
  select: {
    cellEditor: SelectCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorParams: (column) => ({
      columnId: column.id,
      options: selectColumnOptions(column),
    }),
    cellEditorPopup: true,
  },
  // User directive 2026-07-23: a to-many `/for-select` picker — the in-grid
  // twin of the form's multi-select field, scope lock and warning included.
  // Same `relation` metadata as the single-value editor: `resource` names the
  // endpoint, `scope` the row column narrowing it (an array of ids here).
  multiselect: {
    cellEditor: MultiSelectCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorParams: (column) => ({
      resource: column.relation?.resource ?? '',
      scope: column.relation?.scope,
      lockScope: column.relation?.lockScope ?? false,
    }),
    cellEditorPopup: true,
  },
  // Spec 0075: the {funzione aziendale, categoria prodotto} collection, edited
  // in the same two-step flow as the form. It needs no `cellEditorParams`: its
  // two `/for-select` resources ARE what the editor is, and the pairs it edits
  // are the cell's own value.
  product_lines: {
    cellEditor: ProductLinesCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorPopup: true,
  },
  // User directive 2026-09-07: the whole collection of an offer's REVENUE
  // rows. Its component renders nothing in the grid — it delegates to the
  // dialog the request-management screen mounts and closes itself — because a
  // row is composed with pickers that cannot survive inside a cell popup (see
  // `OfferLinesCellEditor`). Registering it here is what keeps the GESTURE and
  // the per-row gate identical to every other editable column.
  offer_lines: {
    cellEditor: OfferLinesCellEditor as ComponentType<CustomCellEditorProps>,
  },
  relation: {
    // AG Grid itself types `ColDef.cellEditor` as `any` (the shape differs by
    // wrapper/framework); this cast is the same interop boundary, confined to
    // this one registration. The component's extra `resource` prop arrives
    // dynamically via `cellEditorParams` below.
    cellEditor: RelationCellEditor as ComponentType<CustomCellEditorProps>,
    cellEditorParams: (column) => {
      const resource = column.relation?.resource ?? ''
      // Same opt-in the form selects make at their call site: only the people
      // resource shows avatars, and it shows one for EVERY option (initials
      // when the user has no image).
      //
      // `scope` is passed through unresolved on purpose: it names the column
      // to read, and only the editor holds the ROW (`props.data`) — this
      // callback runs once per colDef, not per row.
      return {
        resource,
        showAvatar: resource === USERS_FOR_SELECT_RESOURCE,
        scope: column.relation?.scope,
      }
    },
    cellEditorPopup: true,
  },
}

/**
 * Defensive lookup (AC-023): the resolved kind arrives from backend JSON, not
 * a compile-time-checked union, so a kind the frontend does not (yet) know
 * about must resolve to "no editor" instead of indexing past the registry.
 */
export function resolveCellEditorSpec(kind: CellEditorKind): CellEditorSpec | undefined {
  return Object.prototype.hasOwnProperty.call(CELL_EDITOR_REGISTRY, kind)
    ? CELL_EDITOR_REGISTRY[kind]
    : undefined
}
