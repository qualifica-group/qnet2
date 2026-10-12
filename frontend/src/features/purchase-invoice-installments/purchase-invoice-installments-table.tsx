import { useCallback, useRef } from 'react'
import { CalendarClock, HandCoins, Undo2 } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { PURCHASE_INSTALLMENTS_DOMAIN } from '@/features/purchase-invoice-installments/api'
import { purchaseInstallmentColumnRenderers } from '@/features/purchase-invoice-installments/column-renderers'
import { PurchaseClearPaymentDialog } from '@/features/purchase-invoice-installments/purchase-clear-payment-dialog'
import { PurchaseInstallmentBulkPaymentDialog } from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment-dialog'
import { isBulkPayable } from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import { PurchaseInstallmentEditDialog } from '@/features/purchase-invoice-installments/purchase-installment-edit-dialog'
import { PurchaseInstallmentQuickFilterToolbar } from '@/features/purchase-invoice-installments/purchase-installment-quick-filter-toolbar'
import { PurchaseInstallmentTotalsFooter } from '@/features/purchase-invoice-installments/purchase-installment-totals-footer'
import { PurchasePaymentDialog } from '@/features/purchase-invoice-installments/purchase-payment-dialog'
import { usePurchaseInstallmentBulkPayment } from '@/features/purchase-invoice-installments/use-purchase-installment-bulk-payment'
import { usePurchaseInstallmentQuickFilter } from '@/features/purchase-invoice-installments/use-purchase-installment-quick-filter'
import { usePurchaseInstallmentRowActions } from '@/features/purchase-invoice-installments/use-purchase-installment-row-actions'
import type { ActionIconMap } from '@/features/table/action-icon-map'
import { TableView, type TableViewHandle } from '@/features/table/table-view'

/** Icon keys the backend action catalog may use for the installment actions. */
const PURCHASE_ACTION_ICONS: ActionIconMap = {
  'calendar-clock': CalendarClock,
  'hand-coins': HandCoins,
  'undo-2': Undo2,
}

/**
 * Thin payable Scadenze adapter over the generic table: the quick filter writes
 * into the grid filter model, totals come from `meta.aggregates`, row grouping
 * is driven by the table config; grouped by supplier, the selected installments
 * can be paid at once (spec 0211 D-8). Every write re-authorizes server-side.
 */
export function PurchaseInvoiceInstallmentsTable() {
  const tableRef = useRef<TableViewHandle>(null)
  const quickFilter = usePurchaseInstallmentQuickFilter(tableRef)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const actions = usePurchaseInstallmentRowActions(refreshGrid)
  const bulkPayment = usePurchaseInstallmentBulkPayment(tableRef, refreshGrid)

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader />
      <PurchaseInstallmentQuickFilterToolbar value={quickFilter.quickFilter} onChange={quickFilter.setQuickFilter} />

      <TableView
        ref={tableRef}
        domain={PURCHASE_INSTALLMENTS_DOMAIN}
        renderers={purchaseInstallmentColumnRenderers}
        iconMap={PURCHASE_ACTION_ICONS}
        forcedFilterModel={quickFilter.forcedFilterModel}
        onFilterModelChange={quickFilter.onFilterModelChange}
        onAction={actions.handleAction}
        isRowSelectable={isBulkPayable}
        getBulkActions={bulkPayment.getBulkActions}
        onRowGroupColumnsChange={bulkPayment.onRowGroupColumnsChange}
        renderFooter={(aggregates) => <PurchaseInstallmentTotalsFooter aggregates={aggregates} />}
      />

      <PurchaseInstallmentEditDialog installmentId={actions.editId} onClose={actions.closeEdit} onSaved={refreshGrid} />
      <PurchasePaymentDialog detail={actions.payTarget} onClose={actions.closePay} onSaved={refreshGrid} />
      <PurchaseInstallmentBulkPaymentDialog
        targets={bulkPayment.targets}
        onClose={bulkPayment.close}
        onSaved={bulkPayment.onSaved}
      />
      <PurchaseClearPaymentDialog
        sequence={actions.clearSequence}
        isPending={actions.isClearing}
        onClose={actions.closeClear}
        onConfirm={actions.confirmClear}
      />
    </div>
  )
}
