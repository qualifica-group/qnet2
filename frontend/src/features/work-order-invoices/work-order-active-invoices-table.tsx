import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useAbilities } from '@/features/auth/use-abilities'
import { InvoiceDetailSheet } from '@/features/invoices/invoice-detail-sheet'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InvoiceTypeBadge, PaymentStatusIndicator } from '@/features/invoices/invoice-status-badges'
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
import type { WorkOrderActiveDocument, WorkOrderActiveTotals } from '@/features/work-order-invoices/types'
import { formatDate } from '@/lib/formatting/date-display'

interface WorkOrderActiveInvoicesTableProps {
  rows: WorkOrderActiveDocument[]
  totals: WorkOrderActiveTotals
  onChanged: () => void
}

/** Read-only list of the customer proforma/invoices of the work order (spec 0282): the number opens the document. */
export function WorkOrderActiveInvoicesTable({ rows, totals, onChanged }: WorkOrderActiveInvoicesTableProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [openId, setOpenId] = useState<number | null>(null)
  const canOpen = can('invoices.view')

  if (rows.length === 0) {
    return <EmptyTableMessage message={t('workOrders.invoices.active.empty')} />
  }

  return (
    <>
      <InvoiceTableShell caption={t('workOrders.invoices.active.caption')}>
        <thead className="bg-muted/40 text-[11px] text-muted-foreground">
          <tr>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.type')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.number')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.date')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.externalNumber')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.customer')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.total')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.collected')}</th>
            <th scope="col" className={TH_RIGHT}>{t('workOrders.invoices.columns.residual')}</th>
            <th scope="col" className={TH_LEFT}>{t('workOrders.invoices.columns.paymentStatus')}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className="border-t">
              <td className={TD}><InvoiceTypeBadge type={row.type} /></td>
              <td className={TD}>
                <DocumentNumber label={row.number_label} canOpen={canOpen} onOpen={() => setOpenId(row.id)} />
              </td>
              <td className={TD}>{formatDate(row.document_date)}</td>
              <td className={TD}>{row.external_number ?? '-'}</td>
              <td className={TD}>{row.customer.name}</td>
              <td className={TD_MONEY}>{formatEuro(row.total_amount)}</td>
              <td className={TD_MONEY}>{formatEuro(row.collected_amount)}</td>
              <td className={TD_MONEY}>{formatEuro(row.residual_amount)}</td>
              <td className={TD}><PaymentStatusIndicator status={row.payment_status} /></td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <TotalsRow
            labelSpan={5}
            cells={[
              formatEuro(totals.total_amount),
              formatEuro(totals.collected_amount),
              formatEuro(totals.residual_amount),
              '',
            ]}
          />
        </tfoot>
      </InvoiceTableShell>
      {canOpen ? <InvoiceDetailSheet invoiceId={openId} onClose={() => setOpenId(null)} onChanged={onChanged} /> : null}
    </>
  )
}
