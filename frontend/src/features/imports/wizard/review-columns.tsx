/* eslint-disable react-refresh/only-export-components -- cell-renderer module: mixes AG Grid render components with the pure colDef builder, not a route/page component */
import type {
  ColDef,
  ICellRendererParams,
  IRowNode,
  ValueGetterParams,
  ValueSetterParams,
} from 'ag-grid-community'
import type { TFunction } from 'i18next'
import { AlertTriangle } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { ReviewGeoCell } from '@/features/imports/wizard/review-geo-editor'
import { ReviewOperatorCell } from '@/features/imports/wizard/review-operator-editor'
import { ReviewProductsCell } from '@/features/imports/wizard/review-products-editor'
import { ReviewResolutionCell } from '@/features/imports/wizard/review-resolution-cell'
import { ReviewSiteCell } from '@/features/imports/wizard/review-site-editor'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { ImportRowResolution, ImportRowStatus, ImportRunDetail, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * `colId` prefixes distinguishing the two id spaces a review row value can
 * live in: a mapped field id (`fields[].id`, backend catalog) vs. an extra
 * column's raw file header (`leads.extra_fields` key). They never collide
 * even when a file header happens to match a field id string.
 */
const FIELD_COLUMN_PREFIX = 'field:'
const EXTRA_COLUMN_PREFIX = 'extra:'

/**
 * Review field ids resolved by `GeoRecognizer` (spec 0038): these 4 columns
 * never offer text editing — a click opens the cascade popup instead of
 * `agTextCellEditor`/`onCellValueChanged`.
 */
const GEO_FIELD_IDS: ReadonlySet<string> = new Set(['country', 'region', 'province', 'city'])

/** Badge variant per row status (`App\Enums\ImportRowStatus` mirror). */
const STATUS_BADGE_VARIANT: Record<ImportRowStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  valid: 'secondary',
  warning: 'outline',
  error: 'destructive',
  duplicate: 'outline',
  skipped: 'outline',
}

/** Extra tone (beyond the shadcn variant) for statuses that share the `outline` variant. */
const STATUS_TONE_CLASS: Partial<Record<ImportRowStatus, string>> = {
  warning: 'border-amber-500 text-amber-700 dark:text-amber-400',
  duplicate: 'border-sky-500 text-sky-700 dark:text-sky-400',
  skipped: 'text-muted-foreground',
}

function statusLabel(status: ImportRowStatus): string {
  return i18n.t(`review.status.${status}`, { ns: 'importWizard', defaultValue: status })
}

/**
 * Read-only `status` column cell: a colored badge, plus a compact "edited"
 * marker once the row has been touched inline (AC-023 `is_edited`).
 */
export function ReviewStatusCell({ data }: ICellRendererParams<ImportRunRowItem, ImportRowStatus>) {
  if (!data) return null
  return (
    <div className="flex h-full items-center gap-1.5">
      <Badge variant={STATUS_BADGE_VARIANT[data.status]} className={STATUS_TONE_CLASS[data.status]}>
        {statusLabel(data.status)}
      </Badge>
      {data.is_edited ? (
        <span
          className="size-1.5 shrink-0 rounded-full bg-primary"
          title={i18n.t('review.editedTitle', { ns: 'importWizard' })}
        />
      ) : null}
    </div>
  )
}

/** Read-only `messages` column cell: the row's error/warning/interpretation notices. */
export function ReviewMessagesCell({ data }: ICellRendererParams<ImportRunRowItem, unknown>) {
  const messages = data?.messages ?? []
  if (messages.length === 0) {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <div className="flex h-full items-center gap-1 overflow-hidden" title={messages.join('\n')}>
      <AlertTriangle className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
      <span className="truncate text-xs">{messages.join(' · ')}</span>
    </div>
  )
}

/** Strips the `colId` prefix back to the `values` key it edits, or `null` for a non-value column. */
export function reviewValueKeyOf(colId: string): string | null {
  if (colId.startsWith(FIELD_COLUMN_PREFIX)) return colId.slice(FIELD_COLUMN_PREFIX.length)
  if (colId.startsWith(EXTRA_COLUMN_PREFIX)) return colId.slice(EXTRA_COLUMN_PREFIX.length)
  return null
}

/**
 * An editable value column, backed by a `valueGetter`/`valueSetter` pair
 * rather than a flat `field` path: `ImportRunRowItem.values` is a single
 * `Record<string, string>` keyed by field id (mapped) or original column
 * name (extra), not a per-column top-level row property.
 */
function buildValueColumn(
  colId: string,
  valueKey: string,
  headerName: string,
  sortable: boolean,
  editable: boolean,
): ColDef<ImportRunRowItem> {
  return {
    colId,
    headerName,
    editable,
    sortable,
    filter: false,
    minWidth: 160,
    cellEditor: editable ? 'agTextCellEditor' : undefined,
    valueGetter: (params: ValueGetterParams<ImportRunRowItem>) => params.data?.values[valueKey] ?? '',
    valueSetter: (params: ValueSetterParams<ImportRunRowItem>) => {
      if (!params.data || params.newValue === params.oldValue) return false
      params.data.values = { ...params.data.values, [valueKey]: String(params.newValue ?? '') }
      return true
    },
  }
}

/**
 * A geo review field column (spec 0038 AC-010): non-editable, its text comes
 * from the same `values` record as `buildValueColumn`, but the cell renders
 * via `ReviewGeoCell` (popup cascade) instead of `agTextCellEditor`.
 */
function buildGeoColumn(
  colId: string,
  valueKey: string,
  headerName: string,
  sortable: boolean,
  readOnly: boolean,
): ColDef<ImportRunRowItem> {
  return {
    colId,
    headerName,
    editable: false,
    sortable,
    filter: false,
    minWidth: 160,
    valueGetter: (params: ValueGetterParams<ImportRunRowItem>) => params.data?.values[valueKey] ?? '',
    cellRenderer: ReviewGeoCell,
    cellRendererParams: { readOnly },
  }
}

/**
 * The review grid's editable "field" columns: the FINAL persisted fields
 * (`run.review_fields`, spec 0033 delta D-2026-07-15-placeholder-review-fields)
 * — e.g. `first_name`/`last_name` — not the input-only mapped columns
 * (`full_name`). Falls back to the legacy mapped-fields derivation when
 * `review_fields` is absent/empty (retro-compat with runs/fixtures that
 * predate the delta).
 */
function resolveEditableFields(run: ImportRunDetail): Array<{ id: string; label: string }> {
  if (run.review_fields && run.review_fields.length > 0) {
    return run.review_fields
  }
  const mappedFieldIds = new Set(
    Object.values(run.column_mapping ?? {}).filter((target) => target !== IGNORE_TARGET && target !== EXTRA_TARGET),
  )
  return run.fields.filter((field) => mappedFieldIds.has(field.id))
}

/**
 * Builds the review grid's column definitions: `row_number`/`status` are
 * read-only service columns, one column per review field (AC-023, delta
 * D-2026-07-15-placeholder-review-fields) and per `__extra__` column, and a
 * trailing read-only `messages` column. Sorting is only wired on the columns
 * the backend allow-lists (`row_number`, `status`, review field ids) — extra
 * columns and `messages` stay unsortable, matching the SSRM contract.
 *
 * `readOnly` (spec 0034 AC-013) forces every value column to `editable:
 * false`: the concluded-run detail page reuses this exact builder to render
 * the same staged rows without ever offering an edit affordance, on top of
 * the backend's own `PATCH .../rows/{row}` 422 outside `reviewing`.
 *
 * A `resolution` column (spec 0036 AC-008) follows `status`: it renders the
 * matched anagrafica + skip/create/update select for `duplicate` rows via
 * `ReviewResolutionCell`, an em dash for every other row, and stays disabled
 * (no `onResolve`) in `readOnly` mode.
 *
 * An `operator` column follows `resolution`: a non-editable button cell
 * (`ReviewOperatorCell`) showing the row's own operator override, or a
 * muted "uses the default" hint, opening a popup to set/clear it — the
 * apply callback travels via `gridOptions.context`, same as the geo columns.
 *
 * A `site` column follows `operator`, mirroring it exactly (`ReviewSiteCell`)
 * except there is no run-level default to fall back to: the operational site
 * is a per-row-only field, set via this column's popup or the grid's bulk
 * assign bar.
 *
 * A `products` column follows `site` (spec 0094 D-4/AC-055): a non-editable
 * button cell (`ReviewProductsCell`) showing the row's own products-of-interest
 * override, the run's "uses default" hint, or "no products" — opening a popup
 * to set/clear it, scoped to the run's campaign categories.
 */
export function buildReviewColumnDefs(
  run: ImportRunDetail,
  t: TFunction,
  readOnly = false,
  onResolve?: (row: ImportRunRowItem, resolution: ImportRowResolution, node: IRowNode<ImportRunRowItem>) => void,
): ColDef<ImportRunRowItem>[] {
  const mapping = run.column_mapping ?? {}
  const extraColumnNames = Object.entries(mapping)
    .filter(([, target]) => target === EXTRA_TARGET)
    .map(([columnName]) => columnName)
  const editableFields = resolveEditableFields(run)

  return [
    {
      colId: 'row_number',
      field: 'row_number',
      headerName: t('review.columns.rowNumber'),
      editable: false,
      sortable: true,
      filter: false,
      width: 90,
      minWidth: 90,
      flex: 0,
    },
    {
      colId: 'status',
      headerName: t('review.columns.status'),
      editable: false,
      sortable: true,
      filter: false,
      width: 160,
      minWidth: 160,
      flex: 0,
      cellRenderer: ReviewStatusCell,
    },
    {
      colId: 'resolution',
      headerName: t('review.columns.resolution'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 220,
      flex: 0,
      cellRenderer: ReviewResolutionCell,
      cellRendererParams: { onResolve: readOnly ? undefined : onResolve, readOnly },
    },
    {
      colId: 'operator',
      headerName: t('review.columns.operator'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 180,
      flex: 0,
      cellRenderer: ReviewOperatorCell,
      cellRendererParams: { readOnly },
    },
    {
      colId: 'site',
      headerName: t('review.columns.site'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 180,
      flex: 0,
      cellRenderer: ReviewSiteCell,
      cellRendererParams: { readOnly },
    },
    {
      colId: 'products',
      headerName: t('review.columns.products'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 200,
      flex: 0,
      cellRenderer: ReviewProductsCell,
      cellRendererParams: { readOnly },
    },
    // `field.label` is a backend default-namespace i18n key
    // (`imports.leads.fields.*`); resolve it via the default namespace, not the
    // `importWizard`-scoped `t` used for the grid's own chrome.
    ...editableFields.map((field) =>
      GEO_FIELD_IDS.has(field.id)
        ? buildGeoColumn(FIELD_COLUMN_PREFIX + field.id, field.id, i18n.t(field.label), true, readOnly)
        : buildValueColumn(FIELD_COLUMN_PREFIX + field.id, field.id, i18n.t(field.label), true, !readOnly),
    ),
    ...extraColumnNames.map((columnName) =>
      buildValueColumn(
        EXTRA_COLUMN_PREFIX + columnName,
        columnName,
        `${columnName} (${t('review.columns.extraSuffix')})`,
        false,
        !readOnly,
      ),
    ),
    {
      colId: 'messages',
      headerName: t('review.columns.messages'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 220,
      flex: 1,
      cellRenderer: ReviewMessagesCell,
    },
  ]
}
