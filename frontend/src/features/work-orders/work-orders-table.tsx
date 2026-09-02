import { useCallback, useRef } from 'react'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import { workOrderColumnRenderers } from '@/features/work-orders/column-renderers'
import { WORK_ORDERS_DOMAIN } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'

/**
 * Thin work-orders adapter over the generic table. It mounts `<TableView>`
 * with the `work-orders` domain and its custom cell renderers, delegating
 * every row action (view/edit/delete/activity) to `useWorkOrderRowActions`
 * (spec 0095 D-9) — the same behavior the Contract detail's Commesse tab
 * uses, so the two can never drift. Permission gating is an affordance only;
 * the backend re-authorizes each call.
 */
export function WorkOrdersTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const { handleAction, isBusy, activityRow, closeActivity, sheet } = useWorkOrderRowActions({
    onMutated: refreshGrid,
  })

  return (
    <div className="flex flex-1 flex-col gap-4">
      {/*
        Nessuna azione di creazione qui: una commessa nasce sempre dentro il
        perimetro di un'offerta, quindi la pagina elenco non la offre (spec 0093
        D-13). Il PageHeader resta per il breadcrumb.
      */}
      <PageHeader />

      <TableView
        ref={tableRef}
        domain={WORK_ORDERS_DOMAIN}
        renderers={workOrderColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      {sheet}

      <ResourceActivityDialog
        resource={WORK_ORDERS_DOMAIN}
        row={activityRow}
        onOpenChange={closeActivity}
      />
    </div>
  )
}
