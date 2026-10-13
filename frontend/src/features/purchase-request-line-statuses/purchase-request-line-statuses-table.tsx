import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { purchaseRequestLineStatusColumnRenderers } from '@/features/purchase-request-line-statuses/column-renderers'
import {
  PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY,
  PURCHASE_REQUEST_LINE_STATUSES_RESOURCE,
} from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'

/**
 * Thin adapter over the generic table for the line status catalog (spec 0248):
 * no "New" and no delete, only the reorder toggle (every row is reorderable,
 * the shared sheet sends the whole table as `ordered_ids`), the view action
 * (the detail edits in place) and the activity dialog. A reorder or an edit
 * refreshes the grid and the shared catalog. Permission gating is an
 * affordance only; the backend re-authorizes each call.
 */
export function PurchaseRequestLineStatusesTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const tableRef = useRef<TableViewHandle>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const refreshAll = useCallback(() => {
    tableRef.current?.refresh()
    void queryClient.invalidateQueries({ queryKey: PURCHASE_REQUEST_LINE_STATUS_CATALOG_KEY })
  }, [queryClient])

  const { openView, sheet } = useModuleOpener(PURCHASE_REQUEST_LINE_STATUSES_RESOURCE, { onSaved: refreshAll })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      if (action.key === 'view') {
        openView(row)
      } else if (action.key === 'activity') {
        setActivityRow(row)
      }
    },
    [openView],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <StatusReorderToggle
            resource={PURCHASE_REQUEST_LINE_STATUSES_RESOURCE}
            permission="purchase-request-line-statuses.update"
            allRowsReorderable
            labels={{
              openButton: t('purchaseRequestLineStatuses.reorder.openButton'),
              title: t('purchaseRequestLineStatuses.reorder.title'),
              subtitle: t('purchaseRequestLineStatuses.reorder.subtitle'),
              dragHandleLabel: t('purchaseRequestLineStatuses.reorder.dragHandleLabel'),
              inactiveBadge: t('purchaseRequestLineStatuses.reorder.inactiveBadge'),
              loadError: t('purchaseRequestLineStatuses.reorder.loadError'),
              saved: t('purchaseRequestLineStatuses.reorder.saved'),
              forbidden: t('purchaseRequestLineStatuses.reorder.forbidden'),
              genericError: t('purchaseRequestLineStatuses.reorder.genericError'),
            }}
            onReordered={refreshAll}
          />
        }
      />

      <TableView
        ref={tableRef}
        domain={PURCHASE_REQUEST_LINE_STATUSES_RESOURCE}
        renderers={purchaseRequestLineStatusColumnRenderers}
        onAction={handleAction}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PURCHASE_REQUEST_LINE_STATUSES_RESOURCE}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}
