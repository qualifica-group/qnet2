import { useCallback, useRef } from 'react'
import type { ICellRendererParams } from 'ag-grid-community'
import { FilePen, FileText } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { INVOICES_DOMAIN } from '@/features/invoices/api'
import { invoiceColumnRenderers } from '@/features/invoices/column-renderers'
import { InvoiceDeleteDialog } from '@/features/invoices/invoice-delete-dialog'
import { InvoiceDetailPanel } from '@/features/invoices/invoice-detail-panel'
import { InvoiceDetailSheet } from '@/features/invoices/invoice-detail-sheet'
import { InvoiceDetailsDialog } from '@/features/invoices/invoice-details-dialog'
import { InvoiceEditorDialog } from '@/features/invoices/invoice-editor-dialog'
import { InvoiceListToolbar } from '@/features/invoices/invoice-list-toolbar'
import { InvoiceTotalsFooter } from '@/features/invoices/invoice-totals-footer'
import { useInvoiceListFilters } from '@/features/invoices/use-invoice-list-filters'
import { useInvoiceRowActions } from '@/features/invoices/use-invoice-row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { TableRow } from '@/features/table/types'

/** Icon keys the backend catalog uses for the invoice-specific `details` action. */
const INVOICE_ACTION_ICONS: ActionIconMap = { 'file-text': FileText, 'file-pen-line': FilePen }

/**
 * Thin Fatture Attive adapter over the generic table: type tabs and month strip
 * write into the grid filter model, totals come from `meta.aggregates`, the
 * expanded row lazily loads the document. Every write re-authorizes server-side.
 */
export function InvoicesTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const filters = useInvoiceListFilters(tableRef)
  const actions = useInvoiceRowActions()
  // The mutation hooks already invalidate every invoice query (monthly summary, detail): only the SSRM cache needs a purge.
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const renderDetail = useCallback(
    (params: ICellRendererParams<TableRow>) => <InvoiceDetailPanel {...params} onChanged={refreshGrid} />,
    [refreshGrid],
  )

  const { closeEdit } = actions
  const handleSaved = useCallback(() => {
    closeEdit()
    refreshGrid()
  }, [closeEdit, refreshGrid])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <InvoiceListToolbar filters={filters} />

      <TableView
        ref={tableRef}
        domain={INVOICES_DOMAIN}
        renderers={invoiceColumnRenderers}
        iconMap={INVOICE_ACTION_ICONS}
        forcedFilterModel={filters.forcedFilterModel}
        onFilterModelChange={filters.onFilterModelChange}
        onAction={actions.handleAction}
        masterDetail
        detailCellRenderer={renderDetail}
        detailRowAutoHeight
        renderFooter={(aggregates) => <InvoiceTotalsFooter aggregates={aggregates} />}
      />

      <InvoiceDetailSheet invoiceId={actions.viewId} onClose={actions.closeView} onChanged={refreshGrid} />
      <InvoiceDetailsDialog invoiceId={actions.detailsId} onClose={actions.closeDetails} />
      <InvoiceDeleteDialog target={actions.deleteTarget} onClose={actions.closeDelete} onDeleted={refreshGrid} />
      {actions.editId !== null ? (
        <InvoiceEditorDialog
          mode="edit"
          invoiceId={actions.editId}
          open
          onClose={actions.closeEdit}
          onSaved={handleSaved}
        />
      ) : null}
      <ResourceActivityDialog
        resource={INVOICES_DOMAIN}
        row={actions.activityRow}
        onOpenChange={(open) => (open ? undefined : actions.closeActivity())}
      />
    </div>
  )
}
