import { useTranslation } from 'react-i18next'
import { HandCoins, Undo2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import { isPaid } from '@/features/purchase-invoice-installments/purchase-installment-payment'
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

interface PurchaseInvoiceDetailInstallmentsProps {
  installments: PurchaseInvoiceInstallment[]
  /** `purchase-invoices.pay` of the show response; hides the per-installment actions when false. */
  canPay: boolean
  busyInstallmentId: number | null
  onRecord: (installment: PurchaseInvoiceInstallment) => void
  onClear: (installment: PurchaseInvoiceInstallment) => void
}

/**
 * Compact schedule with per-installment "Registra pagamento" / "Annulla pagamento";
 * amounts the actor cannot see (field permissions) are simply blank.
 */
export function PurchaseInvoiceDetailInstallments({
  installments,
  canPay,
  busyInstallmentId,
  onRecord,
  onClear,
}: PurchaseInvoiceDetailInstallmentsProps) {
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
            {canPay ? (
              <th scope="col" className={NUM_HEAD}>{t('purchaseInvoiceViews.detail.installments.columns.actions')}</th>
            ) : null}
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
              {canPay ? (
                <td className="px-2 py-1 text-right">
                  <div className="flex justify-end gap-1">
                    {isPaid(installment) ? (
                      <Button
                        type="button"
                        size="xs"
                        variant="outline"
                        disabled={busyInstallmentId === installment.id}
                        onClick={() => onClear(installment)}
                      >
                        <Undo2 aria-hidden="true" />
                        {t('purchaseInvoiceInstallments.actions.clear_payment')}
                      </Button>
                    ) : (
                      <Button
                        type="button"
                        size="xs"
                        variant="default"
                        disabled={busyInstallmentId === installment.id}
                        onClick={() => onRecord(installment)}
                      >
                        <HandCoins aria-hidden="true" />
                        {t('purchaseInvoiceInstallments.actions.record_payment')}
                      </Button>
                    )}
                  </div>
                </td>
              ) : null}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
