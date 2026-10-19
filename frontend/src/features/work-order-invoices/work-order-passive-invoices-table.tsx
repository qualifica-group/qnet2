import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import { formatEuro } from '@/features/invoices/invoice-format'
import { PurchaseInvoiceTypeBadge } from '@/features/purchase-invoice-installments/purchase-installment-cells'
import { PurchaseInvoiceDetailSheet } from '@/features/purchase-invoices/purchase-invoice-detail-sheet'
import {
  DocumentNumber,
  EmptyTableMessage,
  InvoiceTableShell,
  TD,
  TD_MONEY,
  TH_LEFT,
  TH_RIGHT,
  TotalsRow,
} from '@/features/work-order-invoices/invoice-table-parts'
import type { WorkOrderPassiveDocument, WorkOrderPassiveTotals } from '@/features/work-order-invoices/types'
import { formatDate } from '@/lib/formatting/date-display'

interface WorkOrderPassiveInvoicesTableProps {
  rows: WorkOrderPassiveDocument[]
  totals: WorkOrderPassiveTotals
  onChanged: () => void
}

/** Read-only list of the supplier proforma/invoices with a line on the work order (spec 0282 D-2). */
export function WorkOrderPassiveInvoicesTable({ rows, totals, onChanged }: WorkOrderPassiveInvoicesTableProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [openId, setOpenId] = useState<number | null>(null)
  const canOpen = can('purchase-invoices.view')

  if (rows.length === 0) {
    return <EmptyTableMessage message={t('workOrders.invoices.passive.empty')} />
  }

  return (
    <>
      <InvoiceTableShell caption={t('workOrders.invoices.passive.caption')}>
        <thead className="bg-muted/40 text-[11px] text-muted-foreground">
          <tr>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.type')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.number')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.date')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.supplier')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.documentTotal')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.workOrderAmount')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.paid')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.residual')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className="border-t">
              <td className={TD}><PurchaseInvoiceTypeBadge type={row.document_type} /></td>
              <td className={TD}>
                <DocumentNumber label={row.document_number} canOpen={canOpen} onOpen={() => setOpenId(row.id)} />
              </td>
              <td className={TD}>{formatDate(row.document_date)}</td>
              <td className={TD}>{row.supplier.name}</td>
              <td className={TD_MONEY}>{formatEuro(row.total_amount)}</td>
              <td className={TD_MONEY}>{formatEuro(row.work_order_amount)}</td>
              <td className={TD_MONEY}>{formatEuro(row.paid_amount)}</td>
              <td className={TD_MONEY}>{formatEuro(row.residual_amount)}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <TotalsRow
            labelSpan={4}
            cells={[
              formatEuro(totals.total_amount),
              formatEuro(totals.work_order_amount),
              formatEuro(totals.paid_amount),
              formatEuro(totals.residual_amount),
            ]}
          />
        </tfoot>
      </InvoiceTableShell>
      {canOpen ? (
        <PurchaseInvoiceDetailSheet invoiceId={openId} onClose={() => setOpenId(null)} onChanged={onChanged} />
      ) : null}
    </>
  )
}
