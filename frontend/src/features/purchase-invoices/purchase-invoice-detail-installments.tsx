import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type { PurchaseInstallmentStatus } from '@/features/purchase-invoice-installments/types'
import type { PurchaseInvoiceInstallment } from '@/features/purchase-invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

const STATUS_COLORS: Record<PurchaseInstallmentStatus, string> = {
  unpaid: BADGE_COLOR_CLASSES.slate,
  paid: BADGE_COLOR_CLASSES.green,
}

/** Compact read-only schedule; amounts the actor cannot see (field permissions) are simply blank. */
export function PurchaseInvoiceDetailInstallments({ installments }: { installments: PurchaseInvoiceInstallment[] }) {
  const { t } = useTranslation()

  if (installments.length === 0) {
    return (
      <p className="px-2 py-3 text-xs text-muted-foreground">{t('purchaseInvoiceViews.detail.installments.empty')}</p>
    )
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('purchaseInvoiceViews.detail.installments.title')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.sequence')}</th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.due_date')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.amount')}</th>
            <th scope="col" className={HEAD}>
              {t('purchaseInvoiceViews.detail.installments.columns.payment_method_code')}
            </th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.status')}</th>
            <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.paid_amount')}</th>
            <th scope="col" className={HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.paid_at')}</th>
          </tr>
        </thead>
        <tbody>
          {installments.map((installment) => (
            <tr key={installment.id} className="border-b border-border last:border-b-0">
              <td className={CELL}>{installment.sequence}</td>
              <td className={`${CELL} tabular-nums`}>{formatDate(installment.due_date)}</td>
              <td className={NUM_CELL}>{formatEuro(installment.amount)}</td>
              <td className={CELL}>{installment.payment_method_code ?? ''}</td>
              <td className={CELL}>
                <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[installment.status])}>
                  {t(`enums.purchase_installment_status.${installment.status}`)}
                </Badge>
              </td>
              <td className={NUM_CELL}>{formatEuro(installment.paid_amount)}</td>
              <td className={`${CELL} tabular-nums`}>{formatDate(installment.paid_at)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
