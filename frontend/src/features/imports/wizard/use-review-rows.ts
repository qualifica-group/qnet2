import { useCallback, useMemo } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type {
  CellValueChangedEvent,
  IRowNode,
  IServerSideDatasource,
  IServerSideGetRowsParams,
} from 'ag-grid-community'
import type { GeoValue } from '@/features/geo/geo-select'
import {
  bulkAssignImportRow,
  getImportRunRows,
  resolveImportRunRow,
  updateImportRunRow,
} from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import { reviewValueKeyOf } from '@/features/imports/wizard/review-columns'
import type { ReviewBulkSelectionState } from '@/features/imports/wizard/review-bulk-assign-bar'
import type { AssignOperatorsDialogInput } from '@/features/leads/assign-operators-dialog'
import { resolveAssignFeedback } from '@/features/leads/assign-feedback'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type {
  BulkAssignImportRowPayload,
  BulkAssignImportRowResult,
  ImportRowResolution,
  ImportRunRowCounts,
  ImportRunRowItem,
} from '@/features/imports/wizard/types'

/** Page size fallback when the grid does not provide an explicit block range. */
const DEFAULT_BLOCK_SIZE = 25

/**
 * Maps AG Grid's own server-side selection state and the shared "Assegna
 * operatori" popup's input onto the combined bulk-assign payload (spec
 * 0048): `select_all = state.selectAll`, `row_ids = state.toggledNodes.map
 * (Number)`, `operational_site_id`/`mode` always forwarded, `operator_id`
 * only for `mode: 'single'` (the backend requires it exactly then — see
 * `BulkAssignRequest`). A pure function so the two selection shapes (partial
 * vs. select-all) stay unit-testable without mounting the grid.
 */
export function buildBulkAssignPayload(
  selection: ReviewBulkSelectionState,
  input: AssignOperatorsDialogInput,
): BulkAssignImportRowPayload {
  return {
    operational_site_id: input.operational_site_id,
    mode: input.mode,
    ...(input.mode === 'single' ? { operator_id: input.operator_id as number } : {}),
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }
}

/**
 * Maps AG Grid's own server-side selection state and the bulk products
 * popup's chosen ids onto the combined bulk-assign payload (spec 0094 bulk
 * delta), mirroring `buildBulkAssignPayload` but for the review bar's
 * "Assegna prodotti" action: `product_ids` only, no operator/site/mode
 * fields. A pure function for the same reason as its sibling —
 * unit-testable without mounting the grid.
 */
export function buildBulkAssignProductsPayload(
  selection: ReviewBulkSelectionState,
  productIds: number[],
): BulkAssignImportRowPayload {
  return {
    product_ids: productIds,
    select_all: selection.selectAll,
    row_ids: selection.toggledNodes.map(Number),
  }
}

interface UseReviewRowsArgs {
  domain: string
  importRunId: number
  /** Called after a successful inline edit or resolution change, with the server-authoritative row and run counts. */
  onRowUpdated: (row: ImportRunRowItem, counts: ImportRunRowCounts) => void
}

/**
 * Owns the review grid's server interactions (AC-023, spec 0036 AC-009): the
 * SSRM datasource reading staged rows (`POST .../rows`), the inline-edit
 * mutation (`PATCH .../rows/{row}`) wired to AG Grid's `onCellValueChanged`,
 * and the duplicate-resolution mutation (`PATCH .../rows/{row}/resolution`)
 * wired to `ReviewResolutionCell`. Rows themselves are NOT cached in
 * TanStack Query — AG Grid owns their lifecycle (its SSRM cache); a
 * successful edit/resolve refreshes only that row's node in place
 * (`rowNode.setData`), a failed edit reverts it the same way, so the rest of
 * the loaded block/scroll position is undisturbed.
 */
export function useReviewRows({ domain, importRunId, onRowUpdated }: UseReviewRowsArgs) {
  const { t } = useTranslation('importWizard')
  const queryClient = useQueryClient()

  const updateRowMutation = useMutation({
    mutationFn: ({ rowId, values }: { rowId: number; values: Record<string, string> }) =>
      updateImportRunRow(domain, importRunId, rowId, { values }),
  })

  const updateRowGeoMutation = useMutation({
    mutationFn: ({ rowId, geo }: { rowId: number; geo: GeoValue }) =>
      updateImportRunRow(domain, importRunId, rowId, { geo }),
  })

  const updateRowOperatorMutation = useMutation({
    mutationFn: ({ rowId, operatorId }: { rowId: number; operatorId: number | null }) =>
      updateImportRunRow(domain, importRunId, rowId, { operator_id: operatorId }),
  })

  const updateRowSiteMutation = useMutation({
    mutationFn: ({ rowId, siteId }: { rowId: number; siteId: number | null }) =>
      updateImportRunRow(domain, importRunId, rowId, { operational_site_id: siteId }),
  })

  const updateRowProductsMutation = useMutation({
    mutationFn: ({ rowId, productIds }: { rowId: number; productIds: number[] | null }) =>
      updateImportRunRow(domain, importRunId, rowId, { product_ids: productIds }),
  })

  const updateRowCampaignMutation = useMutation({
    mutationFn: ({ rowId, campaignId }: { rowId: number; campaignId: number | null }) =>
      updateImportRunRow(domain, importRunId, rowId, { campaign_id: campaignId }),
  })

  const resolveRowMutation = useMutation({
    mutationFn: ({ rowId, resolution }: { rowId: number; resolution: ImportRowResolution }) =>
      resolveImportRunRow(domain, importRunId, rowId, resolution),
  })

  const bulkAssignMutation = useMutation({
    mutationFn: (payload: BulkAssignImportRowPayload) => bulkAssignImportRow(domain, importRunId, payload),
  })

  const datasource = useMemo<IServerSideDatasource<ImportRunRowItem>>(
    () => ({
      async getRows(params: IServerSideGetRowsParams<ImportRunRowItem>): Promise<void> {
        const { request } = params
        const startRow = request.startRow ?? 0
        const endRow = request.endRow ?? startRow + DEFAULT_BLOCK_SIZE
        try {
          const page = await getImportRunRows(domain, importRunId, {
            startRow,
            endRow,
            sortModel: request.sortModel.map((item) => ({ colId: item.colId, sort: item.sort })),
            filterModel:
              request.filterModel && !Array.isArray(request.filterModel)
                ? (request.filterModel as Record<string, unknown>)
                : {},
          })
          params.success({ rowData: page.items, rowCount: page.pagination.total })
        } catch {
          params.fail()
        }
      },
    }),
    [domain, importRunId],
  )

  // Step 1: resolve which `values` key the edited column writes to (null for
  // a read-only column, which never reaches here since none are editable).
  // Step 2: PATCH just that field; on success replace the row with the
  // server's re-validated copy (status/messages/is_edited may all have
  // changed) and bubble the recalculated run counts. On failure, put the
  // pre-edit value back so the grid never shows an unsaved, silently-lost edit.
  const handleCellValueChanged = useCallback(
    (event: CellValueChangedEvent<ImportRunRowItem>) => {
      const valueKey = reviewValueKeyOf(event.column.getColId())
      if (!valueKey || !event.data || event.newValue === event.oldValue) {
        return
      }

      const rowId = event.data.id
      const revertedData: ImportRunRowItem = {
        ...event.data,
        values: { ...event.data.values, [valueKey]: event.oldValue ?? '' },
      }

      updateRowMutation.mutate(
        { rowId, values: { [valueKey]: String(event.newValue ?? '') } },
        {
          onSuccess: (result) => {
            event.node.setData(result.row)
            onRowUpdated(result.row, result.counts)
          },
          onError: () => {
            event.node.setData(revertedData)
          },
        },
      )
    },
    [onRowUpdated, updateRowMutation],
  )

  // Step 1: PATCH the chosen resolution for a `duplicate` row; on success
  // replace it with the server's copy and bubble the recalculated counts —
  // the summary step's own query is invalidated too, since its
  // `duplicate_resolutions` recap depends on rows resolved here.
  // Step 2: on failure, put the row back to its pre-resolve state (the
  // select reflects `row.resolution`) and notify the operator.
  const handleResolutionChange = useCallback(
    (row: ImportRunRowItem, resolution: ImportRowResolution, node: IRowNode<ImportRunRowItem>) => {
      resolveRowMutation.mutate(
        { rowId: row.id, resolution },
        {
          onSuccess: (result) => {
            node.setData(result.row)
            onRowUpdated(result.row, result.counts)
            void queryClient.invalidateQueries({ queryKey: importWizardKeys.summary(domain, importRunId) })
          },
          onError: (error) => {
            node.setData(row)
            toast.error(resolveImportWizardErrorMessage(error, t))
          },
        },
      )
    },
    [domain, importRunId, onRowUpdated, queryClient, resolveRowMutation, t],
  )

  // Step 1: PATCH the popup's 4 current geo ids as a single `geo` block
  // (spec 0038 AC-011), mirroring the inline-edit mutation above but for the
  // whole cascade at once. Step 2: on success replace the row with the
  // server's re-validated copy and bubble the recalculated counts; on
  // failure reject unchanged (no `setData`) so the row stays untouched and
  // the popup — the only caller — can surface the error itself (AC-014).
  const handleApplyGeo = useCallback(
    (row: ImportRunRowItem, geo: GeoValue, node: IRowNode<ImportRunRowItem>) =>
      updateRowGeoMutation.mutateAsync({ rowId: row.id, geo }).then((result) => {
        node.setData(result.row)
        onRowUpdated(result.row, result.counts)
      }),
    [onRowUpdated, updateRowGeoMutation],
  )

  // Step 1: PATCH the popup's chosen operator id (or `null` to revert to the
  // run's global default) as `operator_id`, mirroring `handleApplyGeo`.
  // Step 2: on success replace the row with the server's copy, bubble the
  // recalculated counts, and invalidate the summary query — its
  // `conversion_readiness.rows_without_operator` depends on rows overridden
  // here. On failure reject unchanged (no `setData`) so the popup — the only
  // caller — can surface the error itself.
  const handleApplyOperator = useCallback(
    (row: ImportRunRowItem, operatorId: number | null, node: IRowNode<ImportRunRowItem>) =>
      updateRowOperatorMutation.mutateAsync({ rowId: row.id, operatorId }).then((result) => {
        node.setData(result.row)
        onRowUpdated(result.row, result.counts)
        void queryClient.invalidateQueries({ queryKey: importWizardKeys.summary(domain, importRunId) })
      }),
    [domain, importRunId, onRowUpdated, queryClient, updateRowOperatorMutation],
  )

  // Step 1: PATCH the popup's chosen operational-site id (or `null` to clear
  // the override — there is no run default to revert to) as
  // `operational_site_id`, mirroring `handleApplyOperator`. Step 2: on
  // success replace the row with the server's copy, bubble the recalculated
  // counts, and invalidate the summary query — its
  // `conversion_readiness.rows_without_site` depends on rows overridden here.
  // On failure reject unchanged (no `setData`) so the popup — the only
  // caller — can surface the error itself.
  const handleApplySite = useCallback(
    (row: ImportRunRowItem, siteId: number | null, node: IRowNode<ImportRunRowItem>) =>
      updateRowSiteMutation.mutateAsync({ rowId: row.id, siteId }).then((result) => {
        node.setData(result.row)
        onRowUpdated(result.row, result.counts)
        void queryClient.invalidateQueries({ queryKey: importWizardKeys.summary(domain, importRunId) })
      }),
    [domain, importRunId, onRowUpdated, queryClient, updateRowSiteMutation],
  )

  // Step 1: PATCH the popup's chosen product ids (`null` reverts to the run's
  // global `product_ids` default, `[]` is an explicit "no products on this
  // row" override — the two are distinct, see `ImportRunRowItem.product_ids`)
  // as `product_ids`, mirroring `handleApplySite`. Step 2: on success replace
  // the row with the server's copy and bubble the recalculated counts. On
  // failure reject unchanged (no `setData`) so the popup — the only caller —
  // can surface the error itself.
  const handleApplyProducts = useCallback(
    (row: ImportRunRowItem, productIds: number[] | null, node: IRowNode<ImportRunRowItem>) =>
      updateRowProductsMutation.mutateAsync({ rowId: row.id, productIds }).then((result) => {
        node.setData(result.row)
        onRowUpdated(result.row, result.counts)
      }),
    [onRowUpdated, updateRowProductsMutation],
  )

  // Step 1: PATCH the popup's chosen campaign id (or `null` to unpin it and
  // let the backend re-read the file's own code) as `campaign_id`, mirroring
  // `handleApplyProducts`. Unlike the other per-row popups this one can
  // change the row's STATUS (an unmatched row becomes valid, and vice versa),
  // which is why the summary query is invalidated too: the conversion
  // readiness is computed across the campaigns of the rows (spec 0108 D-8).
  // On failure reject unchanged (no `setData`) so the popup — the only
  // caller — can surface the error itself.
  const handleApplyCampaign = useCallback(
    (row: ImportRunRowItem, campaignId: number | null, node: IRowNode<ImportRunRowItem>) =>
      updateRowCampaignMutation.mutateAsync({ rowId: row.id, campaignId }).then((result) => {
        node.setData(result.row)
        onRowUpdated(result.row, result.counts)
        void queryClient.invalidateQueries({ queryKey: importWizardKeys.summary(domain, importRunId) })
      }),
    [domain, importRunId, onRowUpdated, queryClient, updateRowCampaignMutation],
  )

  // Step 1: PATCH the combined bulk assignment (operator and/or site, or
  // products — spec 0094 bulk delta) for the current selection
  // (`select_all`/`row_ids` mirror AG Grid's own server-side selection state
  // 1:1 — built by the caller via `buildBulkAssignPayload`/
  // `buildBulkAssignProductsPayload`). Step 2: on success, invalidate the
  // summary query (its `conversion_readiness.rows_without_operator`/
  // `rows_without_site` depend on this) and notify with a toast; the caller
  // (which owns the grid api) still has to refresh the SSRM cache and clear
  // the selection itself. On failure, toast the error and rethrow so the
  // caller's own pending state can react.
  const handleBulkAssign = useCallback(
    (payload: BulkAssignImportRowPayload): Promise<BulkAssignImportRowResult> =>
      bulkAssignMutation.mutateAsync(payload).then(
        (result) => {
          void queryClient.invalidateQueries({ queryKey: importWizardKeys.summary(domain, importRunId) })
          toast.success(
            resolveAssignFeedback(t, 'review.bulkAssign', { assigned: result.updated, skipped: result.skipped }),
          )
          return result
        },
        (error: unknown) => {
          toast.error(resolveImportWizardErrorMessage(error, t))
          throw error
        },
      ),
    [bulkAssignMutation, domain, importRunId, queryClient, t],
  )

  return {
    datasource,
    handleCellValueChanged,
    handleResolutionChange,
    handleApplyGeo,
    handleApplyOperator,
    handleApplySite,
    handleApplyProducts,
    handleApplyCampaign,
    handleBulkAssign,
    isSaving: updateRowMutation.isPending,
    hasSaveError: updateRowMutation.isError,
  }
}
