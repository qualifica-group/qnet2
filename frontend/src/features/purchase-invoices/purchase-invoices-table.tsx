import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { FilePen, FileText, Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { Can } from '@/features/auth/can'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { purchaseInvoiceColumnRenderers } from '@/features/purchase-invoices/column-renderers'
import { PurchaseInvoiceActionDialogs } from '@/features/purchase-invoices/purchase-invoice-action-dialogs'
import { PurchaseInvoiceDetailPanel } from '@/features/purchase-invoices/purchase-invoice-detail-panel'
import { PurchaseInvoiceDetailSheet } from '@/features/purchase-invoices/purchase-invoice-detail-sheet'
import { PurchaseInvoiceEditorDialog } from '@/features/purchase-invoices/purchase-invoice-editor-dialog'
import { usePurchaseInvoiceRowActions } from '@/features/purchase-invoices/use-purchase-invoice-row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRow } from '@/features/table/types'

/** Icon keys the backend catalog uses for the document-specific `details`/`update` actions. */
const PURCHASE_INVOICE_ACTION_ICONS: ActionIconMap = {
  'file-text': FileText,
  'file-pen-line': FilePen,
}

function renderDetail(params: ICellRendererParams<TableRow>) {
  return <PurchaseInvoiceDetailPanel {...params} />
}

/**
 * Thin passive-invoices adapter over the generic table: one row per document,
 * actions come from the server row (view, update, details, delete, activity),
 * the expanded row lazily loads lines and installments. Every write re-authorizes server-side.
 */
export function PurchaseInvoicesTable() {
  const { t } = useTranslation()
  const tableRef = useRef<TableViewHandle>(null)
  const actions = usePurchaseInvoiceRowActions()
  const [isCreating, setIsCreating] = useState(false)
  // The editor and delete mutations already invalidate every document query: only the SSRM cache needs a purge.
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const { closeEdit } = actions
  const handleEdited = useCallback(() => {
    closeEdit()
    refreshGrid()
  }, [closeEdit, refreshGrid])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission={`${PURCHASE_INVOICES_DOMAIN}.create`}>
            <Button onClick={() => setIsCreating(true)}>
              <Plus aria-hidden="true" />
              {t('purchaseInvoiceViews.newInvoice')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={PURCHASE_INVOICES_DOMAIN}
        renderers={purchaseInvoiceColumnRenderers}
        iconMap={PURCHASE_INVOICE_ACTION_ICONS}
        onAction={actions.handleAction}
        masterDetail
        detailCellRenderer={renderDetail}
        detailRowAutoHeight
      />

      <PurchaseInvoiceDetailSheet invoiceId={actions.viewId} onClose={actions.closeView} onChanged={refreshGrid} />
      <PurchaseInvoiceActionDialogs
        editId={actions.editId}
        detailsId={actions.detailsId}
        deleteTarget={actions.deleteTarget}
        onCloseEdit={actions.closeEdit}
        onCloseDetails={actions.closeDetails}
        onCloseDelete={actions.closeDelete}
        onSaved={handleEdited}
        onDeleted={refreshGrid}
      />
      {isCreating ? (
        <PurchaseInvoiceEditorDialog
          mode="create"
          open
          onOpenChange={(open) => (open ? undefined : setIsCreating(false))}
          onSaved={refreshGrid}
        />
      ) : null}
      <ResourceActivityDialog
        resource={PURCHASE_INVOICES_DOMAIN}
        row={actions.activityRow}
        onOpenChange={(open) => (open ? undefined : actions.closeActivity())}
      />
    </div>
  )
}
