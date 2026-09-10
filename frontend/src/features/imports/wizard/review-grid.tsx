import { useCallback, useMemo, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AgGridReact } from 'ag-grid-react'
import {
  themeQuartz,
  type GetRowIdParams,
  type GridApi,
  type GridOptions,
  type GridReadyEvent,
  type IServerSideSelectionState,
  type RowSelectionOptions,
  type SelectionChangedEvent,
} from 'ag-grid-community'
import { AG_GRID_LOCALE_EN, AG_GRID_LOCALE_IT } from '@ag-grid-community/locale'
import { setupAgGrid } from '@/components/data-table/ag-grid-setup'
import { buildReviewColumnDefs } from '@/features/imports/wizard/review-columns'
import { ReviewBulkAssignBar, type ReviewBulkSelectionState } from '@/features/imports/wizard/review-bulk-assign-bar'
import type { ReviewCampaignGridContext } from '@/features/imports/wizard/review-campaign-editor'
import type { ReviewGeoGridContext } from '@/features/imports/wizard/review-geo-editor'
import type { ReviewOperatorGridContext } from '@/features/imports/wizard/review-operator-editor'
import type { ReviewProductsGridContext } from '@/features/imports/wizard/review-products-editor'
import type { ReviewSiteGridContext } from '@/features/imports/wizard/review-site-editor'
import {
  buildBulkAssignPayload,
  buildBulkAssignProductsPayload,
  useReviewRows,
} from '@/features/imports/wizard/use-review-rows'
import { useReviewProductsScope } from '@/features/imports/wizard/use-review-products-scope'
import type { AssignOperatorsDialogInput } from '@/features/leads/assign-operators-dialog'
import type { ImportRunDetail, ImportRunRowCounts, ImportRunRowItem } from '@/features/imports/wizard/types'

// Register enterprise modules + license once, at module load (idempotent,
// mirrors `components/data-table/data-table.tsx`).
setupAgGrid()

/** Rows fetched per SSRM block; the review dataset is typically small (a single import file). */
const REVIEW_BLOCK_SIZE = 25

/**
 * Compact theme aligned to the app's design tokens (§ui-design), scoped to
 * this grid: `components/data-table/data-table.tsx` owns its own private
 * theme and is not touched here (read-only grids are out of this lane's
 * scope) — a small, self-contained token set is intentional, not duplication
 * of shared logic.
 */
const reviewGridTheme = themeQuartz.withParams({
  fontFamily: 'inherit',
  fontSize: 12,
  rowHeight: 30,
  headerHeight: 32,
  headerFontSize: 10,
  headerFontWeight: 600,
  cellHorizontalPadding: 10,
  backgroundColor: 'var(--card)',
  foregroundColor: 'var(--card-foreground)',
  // Softened like `components/data-table/data-table-theme.ts`: the shared
  // hairline is sized for card edges, not for one line per cell, and the tint
  // sits beyond the end of the surface ladder to be felt on any host.
  borderColor: 'color-mix(in srgb, var(--border) 40%, var(--card))',
  headerBackgroundColor: 'var(--card)',
  headerTextColor: 'var(--muted-foreground)',
  rowHoverColor: 'color-mix(in srgb, var(--muted) 65%, var(--card))',
  wrapperBorderRadius: 6,
})

/** No-op counters callback for the read-only mode, which never edits a row. */
function noopRowUpdated(): void {}

/** `global_fields` id carrying the run's default operator (`Leads/LeadImportFieldCatalog::globalConfig`). */
const GLOBAL_OPERATOR_FIELD_ID = 'operator_id'

/** No selection: the bulk-assign bar stays hidden and the bar's own payload never applies. */
const EMPTY_SELECTION: ReviewBulkSelectionState = { selectAll: false, toggledNodes: [] }

/**
 * Checkbox multi-selection (bulk operator assign), `selectAll: 'all'` so the
 * header checkbox drives a true server-side "select all" — not capped to the
 * loaded page — matching the bulk-assign contract 1:1
 * (`gridApi.getServerSideSelectionState()`). Disabled entirely in `readOnly`
 * mode (the concluded-run detail page never bulk-assigns).
 */
const ROW_SELECTION: RowSelectionOptions<ImportRunRowItem> = {
  mode: 'multiRow',
  checkboxes: true,
  headerCheckbox: true,
  selectAll: 'all',
}

/** Reads the run's global default operator id (`global_config.operator_id`), or `null` when unset. */
function resolveGlobalDefaultOperatorId(run: ImportRunDetail): number | null {
  const raw = run.global_config?.[GLOBAL_OPERATOR_FIELD_ID]
  return typeof raw === 'number' ? raw : null
}

export interface ReviewGridProps {
  domain: string
  run: ImportRunDetail
  /** Bubbled up from an inline edit so the caller (review step) can refresh its counters. */
  onRowUpdated?: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
  /**
   * Renders the same staged rows with every value column non-editable and no
   * `onCellValueChanged` wiring (spec 0034 AC-013): the concluded-run detail
   * page reuses this grid purely as a read-only viewer, on top of the
   * backend's own `PATCH .../rows/{row}` 422 outside `reviewing`.
   */
  readOnly?: boolean
}

/**
 * AG Grid SSRM datasource over the staged rows of a run (AC-023), with
 * inline editing on the mapped/extra value columns while `reviewing`:
 * `stopEditing` (AG Grid's own commit-on-blur/Enter) fires
 * `onCellValueChanged`, which PATCHes just the edited field and swaps the row
 * for the server's re-validated copy. `readOnly` renders the same rows
 * without any edit affordance (spec 0034).
 */
export function ReviewGrid({ domain, run, onRowUpdated = noopRowUpdated, readOnly = false }: ReviewGridProps) {
  const { t } = useTranslation('importWizard')
  const { i18n } = useTranslation()
  const gridApiRef = useRef<GridApi<ImportRunRowItem> | null>(null)
  const [selection, setSelection] = useState<ReviewBulkSelectionState>(EMPTY_SELECTION)

  const localeText = useMemo(
    () => (i18n.language.startsWith('it') ? AG_GRID_LOCALE_IT : AG_GRID_LOCALE_EN),
    [i18n.language],
  )

  const {
    datasource,
    handleCellValueChanged,
    handleResolutionChange,
    handleApplyGeo,
    handleApplyOperator,
    handleApplySite,
    handleApplyProducts,
    handleApplyCampaign,
    handleBulkAssign: handleBulkAssignRows,
  } = useReviewRows({
    domain,
    importRunId: run.id,
    onRowUpdated,
  })

  const columnDefs = useMemo(
    () => buildReviewColumnDefs(run, t, readOnly, handleResolutionChange),
    [run, t, readOnly, handleResolutionChange],
  )

  const getRowId = useCallback((params: GetRowIdParams<ImportRunRowItem>) => String(params.data.id), [])

  const globalDefaultOperatorId = useMemo(() => resolveGlobalDefaultOperatorId(run), [run])
  const { globalDefaultProductIds, campaignCategoryIds } = useReviewProductsScope(run)

  // The geo/operator/site/products popups' apply callbacks are shared by all
  // 4 geo columns, the operator column, the site column and the products
  // column respectively, never column-specific, so they travel via
  // `gridOptions.context` instead of `cellRendererParams` — every
  // `ReviewGeoCell`/`ReviewOperatorCell`/`ReviewSiteCell`/`ReviewProductsCell`
  // reads them off `params.context`, with no per-colDef prop drilling.
  // `globalDefaultOperatorId` is read once here from the run, not
  // prop-drilled per column either; `globalDefaultSiteId` is always `null`
  // (the operational site has no global-config default, spec delta).
  const gridContext = useMemo<
    ReviewGeoGridContext &
      ReviewOperatorGridContext &
      ReviewSiteGridContext &
      ReviewProductsGridContext &
      ReviewCampaignGridContext
  >(
    () => ({
      onApplyGeo: handleApplyGeo,
      onApplyOperator: handleApplyOperator,
      globalDefaultOperatorId,
      importRunId: run.id,
      onApplySite: handleApplySite,
      globalDefaultSiteId: null,
      onApplyProducts: handleApplyProducts,
      onApplyCampaign: handleApplyCampaign,
      hasGlobalDefaultProducts: globalDefaultProductIds.length > 0,
      campaignCategoryIds,
    }),
    [
      handleApplyGeo,
      handleApplyOperator,
      globalDefaultOperatorId,
      run.id,
      handleApplySite,
      handleApplyProducts,
      handleApplyCampaign,
      globalDefaultProductIds,
      campaignCategoryIds,
    ],
  )

  const handleGridReady = useCallback((event: GridReadyEvent<ImportRunRowItem>) => {
    gridApiRef.current = event.api
  }, [])

  // Reads AG Grid's own server-side selection state after every toggle
  // (single row or header "select all") rather than tracking it by hand —
  // `{ selectAll, toggledNodes }` is exactly the shape the bulk-assign
  // payload needs (spec: mirrors `gridApi.getServerSideSelectionState()` 1:1).
  // The selection derives no Sede any more: the popup asks the server for the
  // scope of exactly these rows (spec 0113 AC-033).
  const handleSelectionChanged = useCallback((event: SelectionChangedEvent<ImportRunRowItem>) => {
    const state = event.api.getServerSideSelectionState() as IServerSideSelectionState | null
    setSelection(state ? { selectAll: state.selectAll, toggledNodes: state.toggledNodes } : EMPTY_SELECTION)
  }, [])

  // Step 1: PATCH the combined bulk assignment (mode + operator) from the
  // current selection and the shared popup's input. Step 2: on success,
  // refresh the SSRM cache (purge so the Operator/Sede columns re-fetch the
  // server's copy) and clear the selection; on failure, leave the selection
  // and grid untouched (the mutation already toasted the error) so the
  // operator can retry.
  const handleBulkAssign = useCallback(
    (input: AssignOperatorsDialogInput) =>
      handleBulkAssignRows(buildBulkAssignPayload(selection, input)).then(() => {
        gridApiRef.current?.setServerSideSelectionState(EMPTY_SELECTION)
        gridApiRef.current?.refreshServerSide({ purge: true })
      }),
    [handleBulkAssignRows, selection],
  )

  // Same success/refresh/clear-selection contract as `handleBulkAssign`
  // above (spec 0094 bulk delta), the "Assegna prodotti" dropdown action.
  const handleBulkAssignProducts = useCallback(
    (productIds: number[]) =>
      handleBulkAssignRows(buildBulkAssignProductsPayload(selection, productIds)).then(() => {
        gridApiRef.current?.setServerSideSelectionState(EMPTY_SELECTION)
        gridApiRef.current?.refreshServerSide({ purge: true })
      }),
    [handleBulkAssignRows, selection],
  )

  const gridOptions = useMemo<GridOptions<ImportRunRowItem>>(
    () => ({
      rowModelType: 'serverSide',
      serverSideDatasource: datasource,
      cacheBlockSize: REVIEW_BLOCK_SIZE,
      serverSideInitialRowCount: REVIEW_BLOCK_SIZE,
      pagination: true,
      paginationPageSize: REVIEW_BLOCK_SIZE,
      paginationPageSizeSelector: [REVIEW_BLOCK_SIZE, REVIEW_BLOCK_SIZE * 2],
      defaultColDef: { resizable: true, minWidth: 120 },
      getRowId,
      context: gridContext,
      singleClickEdit: !readOnly,
      stopEditingWhenCellsLoseFocus: !readOnly,
      onCellValueChanged: readOnly ? undefined : handleCellValueChanged,
      rowSelection: readOnly ? undefined : ROW_SELECTION,
      onSelectionChanged: readOnly ? undefined : handleSelectionChanged,
    }),
    [datasource, getRowId, gridContext, handleCellValueChanged, handleSelectionChanged, readOnly],
  )

  const hasSelection = selection.selectAll || selection.toggledNodes.length > 0

  return (
    <div className="flex flex-col gap-2">
      {hasSelection ? (
        <ReviewBulkAssignBar
          selection={selection}
          importRunId={run.id}
          totalRows={run.total_rows}
          campaignCategoryIds={campaignCategoryIds}
          onAssign={handleBulkAssign}
          onAssignProducts={handleBulkAssignProducts}
        />
      ) : null}
      <div
        className="flex h-[55vh] min-h-[320px] max-h-[640px] w-full flex-col"
        role="region"
        aria-label={t('review.gridLabel')}
      >
        <AgGridReact
          theme={reviewGridTheme}
          columnDefs={columnDefs}
          localeText={localeText}
          onGridReady={handleGridReady}
          {...gridOptions}
        />
      </div>
    </div>
  )
}
