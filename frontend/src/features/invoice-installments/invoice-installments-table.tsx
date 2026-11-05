import { useCallback, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CalendarClock, Eye, HandCoins, Scale, Undo2 } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { CompensationDialog } from '@/features/compensations/compensation-dialog'
import { useInstallmentActionState } from '@/features/compensations/use-installment-action-state'
import { INVOICE_INSTALLMENTS_DOMAIN } from '@/features/invoice-installments/api'
import { installmentColumnRenderers } from '@/features/invoice-installments/column-renderers'
import { InstallmentBulkCollectionDialog } from '@/features/invoice-installments/installment-bulk-collection-dialog'
import { isBulkCollectable } from '@/features/invoice-installments/installment-bulk-collection'
import { InstallmentQuickFilterToolbar } from '@/features/invoice-installments/installment-quick-filter-toolbar'
import { InstallmentTotalsFooter } from '@/features/invoice-installments/installment-totals-footer'
import { useInstallmentBulkCollection } from '@/features/invoice-installments/use-installment-bulk-collection'
import { parseQuickFilterParam, useInstallmentQuickFilter } from '@/features/invoice-installments/use-installment-quick-filter'
import { useInstallmentRowActions } from '@/features/invoice-installments/use-installment-row-actions'
import { InvoiceClearCollectionDialog } from '@/features/invoices/invoice-clear-collection-dialog'
import { InvoiceCollectionDialog } from '@/features/invoices/invoice-collection-dialog'
import { InvoiceDetailSheet } from '@/features/invoices/invoice-detail-sheet'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'

/** Collection actions a ticked (reconciled) installment cannot take (spec 0363 D-2). */
const RECONCILED_LOCKED_ACTIONS = ['clear_collection', 'record_collection'] as const
/** Actions a compensated installment cannot take: it is undone from the compensation (spec 0364 D-7). */
const COMPENSATED_LOCKED_ACTIONS = ['clear_collection'] as const
/** URL parameter that opens the grid on a quick filter (the treasury overview links here). */
const QUICK_PARAM = 'quick'

/** Icon keys the backend action catalog may use for the installment-specific actions. */
const INSTALLMENT_ACTION_ICONS: ActionIconMap = {
  eye: Eye,
  'calendar-clock': CalendarClock,
  'hand-coins': HandCoins,
  'undo-2': Undo2,
  scale: Scale,
}

/**
 * Thin Scadenze adapter over the generic table: the quick filter writes into
 * the grid filter model, totals come from `meta.aggregates`, row grouping is
 * driven by the table config; grouped by customer, the selected installments
 * can be collected at once (spec 0198). Every write re-authorizes server-side.
 */
export function InvoiceInstallmentsTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const [searchParams] = useSearchParams()
  const quickFilter = useInstallmentQuickFilter(tableRef, parseQuickFilterParam(searchParams.get(QUICK_PARAM)))
  const resolveActionState = useInstallmentActionState(RECONCILED_LOCKED_ACTIONS, COMPENSATED_LOCKED_ACTIONS)
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
        resolveActionState={resolveActionState}
        isRowSelectable={isBulkCollectable}
        getBulkActions={bulkCollection.getBulkActions}
        onRowGroupColumnsChange={bulkCollection.onRowGroupColumnsChange}
        renderFooter={(aggregates) => <InstallmentTotalsFooter aggregates={aggregates} />}
      />

      <CompensationDialog target={actions.compensationTarget} onClose={actions.closeCompensation} />
      <InvoiceDetailSheet invoiceId={actions.viewInvoiceId} onClose={actions.closeView} onChanged={refreshGrid} />
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
