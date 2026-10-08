import { useCallback, useRef } from 'react'
import { CalendarClock, Eye, HandCoins, Undo2 } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { INVOICE_INSTALLMENTS_DOMAIN } from '@/features/invoice-installments/api'
import { installmentColumnRenderers } from '@/features/invoice-installments/column-renderers'
import { InstallmentBulkCollectionDialog } from '@/features/invoice-installments/installment-bulk-collection-dialog'
import { isBulkCollectable } from '@/features/invoice-installments/installment-bulk-collection'
import { InstallmentEditDialog } from '@/features/invoice-installments/installment-edit-dialog'
import { InstallmentQuickFilterToolbar } from '@/features/invoice-installments/installment-quick-filter-toolbar'
import { InstallmentTotalsFooter } from '@/features/invoice-installments/installment-totals-footer'
import { useInstallmentBulkCollection } from '@/features/invoice-installments/use-installment-bulk-collection'
import { useInstallmentQuickFilter } from '@/features/invoice-installments/use-installment-quick-filter'
import { useInstallmentRowActions } from '@/features/invoice-installments/use-installment-row-actions'
import { InvoiceClearCollectionDialog } from '@/features/invoices/invoice-clear-collection-dialog'
import { InvoiceCollectionDialog } from '@/features/invoices/invoice-collection-dialog'
import { InvoiceDetailSheet } from '@/features/invoices/invoice-detail-sheet'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'

/** Icon keys the backend action catalog may use for the installment-specific actions. */
const INSTALLMENT_ACTION_ICONS: ActionIconMap = {
  eye: Eye,
  'calendar-clock': CalendarClock,
  'hand-coins': HandCoins,
  'undo-2': Undo2,
}

/**
 * Thin Scadenze adapter over the generic table: the quick filter writes into
 * the grid filter model, totals come from `meta.aggregates`, row grouping is
 * driven by the table config; grouped by customer, the selected installments
 * can be collected at once (spec 0198). Every write re-authorizes server-side.
 */
export function InvoiceInstallmentsTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const quickFilter = useInstallmentQuickFilter(tableRef)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const actions = useInstallmentRowActions(refreshGrid)
  const { collection } = actions
  const bulkCollection = useInstallmentBulkCollection(tableRef, refreshGrid)

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <InstallmentQuickFilterToolbar value={quickFilter.quickFilter} onChange={quickFilter.setQuickFilter} />

      <TableView
        ref={tableRef}
        domain={INVOICE_INSTALLMENTS_DOMAIN}
        renderers={installmentColumnRenderers}
        iconMap={INSTALLMENT_ACTION_ICONS}
        forcedFilterModel={quickFilter.forcedFilterModel}
        onFilterModelChange={quickFilter.onFilterModelChange}
        onAction={actions.handleAction}
        isRowSelectable={isBulkCollectable}
        getBulkActions={bulkCollection.getBulkActions}
        onRowGroupColumnsChange={bulkCollection.onRowGroupColumnsChange}
        renderFooter={(aggregates) => <InstallmentTotalsFooter aggregates={aggregates} />}
      />

      <InvoiceDetailSheet invoiceId={actions.viewInvoiceId} onClose={actions.closeView} onChanged={refreshGrid} />
      <InstallmentEditDialog installmentId={actions.editId} onClose={actions.closeEdit} onSaved={refreshGrid} />
      <InvoiceCollectionDialog
        installment={collection.collectTarget}
        installments={actions.documentInstallments}
        onClose={collection.closeCollect}
        onSaved={refreshGrid}
      />
      <InstallmentBulkCollectionDialog
        targets={bulkCollection.targets}
        onClose={bulkCollection.close}
        onSaved={bulkCollection.onSaved}
      />
      <InvoiceClearCollectionDialog
        target={collection.clearTarget}
        isPending={collection.isClearing}
        onClose={collection.closeClear}
        onConfirm={collection.confirmClear}
      />
    </div>
  )
}
