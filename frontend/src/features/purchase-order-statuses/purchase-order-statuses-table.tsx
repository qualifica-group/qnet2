import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { purchaseOrderStatusColumnRenderers } from '@/features/purchase-order-statuses/column-renderers'
import { PURCHASE_ORDER_STATUSES_RESOURCE } from '@/features/purchase-order-statuses/types'
import { StatusReorderToggle } from '@/features/status-reorder/status-reorder-toggle'

/**
 * Thin ODA status adapter over the generic table (spec 0248): it mounts
 * `<TableView domain="purchase-order-statuses">` and opens the detail through
 * `useModuleOpener`. There is no create and no delete: the 7 rows belong to
 * the enum. The reorder toggle is on `allRowsReorderable`, because here the
 * `system_key` ties a row to its enum value and pins nothing. Permission
 * gating is an affordance only; the backend re-authorizes each call.
 */
export function PurchaseOrderStatusesTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)
  const { openView, sheet } = useModuleOpener(PURCHASE_ORDER_STATUSES_RESOURCE, { onSaved: refreshGrid })

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          openView(row)
          break
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [openView],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <StatusReorderToggle
            resource={PURCHASE_ORDER_STATUSES_RESOURCE}
            permission="purchase-order-statuses.update"
            allRowsReorderable
            labels={{
              openButton: t('purchaseOrderStatuses.reorder.openButton'),
              title: t('purchaseOrderStatuses.reorder.title'),
              subtitle: t('purchaseOrderStatuses.reorder.subtitle'),
              dragHandleLabel: t('purchaseOrderStatuses.reorder.dragHandleLabel'),
              loadError: t('purchaseOrderStatuses.reorder.loadError'),
              saved: t('purchaseOrderStatuses.reorder.saved'),
              forbidden: t('purchaseOrderStatuses.reorder.forbidden'),
              genericError: t('purchaseOrderStatuses.reorder.genericError'),
            }}
            onReordered={refreshGrid}
          />
        }
      />

      <TableView
        ref={tableRef}
        domain={PURCHASE_ORDER_STATUSES_RESOURCE}
        renderers={purchaseOrderStatusColumnRenderers}
        onAction={handleAction}
      />

      {sheet}

      <ResourceActivityDialog
        resource={PURCHASE_ORDER_STATUSES_RESOURCE}
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
