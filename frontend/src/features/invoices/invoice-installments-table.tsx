import { useTranslation } from 'react-i18next'
import { HandCoins, Undo2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatDate } from '@/lib/formatting/date-display'
import { isCollected } from '@/features/invoices/invoice-collection-residual'
import { formatEuro } from '@/features/invoices/invoice-format'
import { InstallmentStatusBadge } from '@/features/invoices/invoice-status-badges'
import type { InvoiceInstallment } from '@/features/invoices/types'

const HEAD = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUM_HEAD = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUM_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

interface InvoiceInstallmentsTableProps {
  installments: InvoiceInstallment[]
  /** `invoices.collect` from the show response; hides the per-installment actions when false. */
  canCollect: boolean
  busyInstallmentId: number | null
  onRecord: (installment: InvoiceInstallment) => void
  onClear: (installment: InvoiceInstallment) => void
}

/** Compact schedule table with per-installment "Registra incasso" / "Annulla incasso". */
export function InvoiceInstallmentsTable({
  installments,
  canCollect,
  busyInstallmentId,
  onRecord,
  onClear,
}: InvoiceInstallmentsTableProps) {
  const { t } = useTranslation()

  if (installments.length === 0) {
    return <p className="px-2 py-3 text-xs text-muted-foreground">{t('invoices.detail.noInstallments')}</p>
  }

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-max border-collapse">
        <caption className="sr-only">{t('invoices.detail.installments')}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={HEAD}>{t('invoices.detail.installmentsColumns.sequence')}</th>
            <th scope="col" className={HEAD}>{t('invoices.detail.installmentsColumns.due_date')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.installmentsColumns.amount')}</th>
            <th scope="col" className={HEAD}>{t('invoices.detail.installmentsColumns.payment_method_code')}</th>
            <th scope="col" className={HEAD}>{t('invoices.detail.installmentsColumns.status')}</th>
            <th scope="col" className={NUM_HEAD}>{t('invoices.detail.installmentsColumns.collected_amount')}</th>
            <th scope="col" className={HEAD}>{t('invoices.detail.installmentsColumns.collected_at')}</th>
            {canCollect ? <th scope="col" className={NUM_HEAD}>{t('invoices.detail.installmentsColumns.actions')}</th> : null}
          </tr>
        </thead>
        <tbody>
          {installments.map((installment) => {
            const hasCollection = isCollected(installment)
            return (
              <tr key={installment.id} className="border-b border-border last:border-b-0">
                <td className={CELL}>{installment.sequence}</td>
                <td className={`${CELL} tabular-nums`}>{formatDate(installment.due_date)}</td>
                <td className={NUM_CELL}>{formatEuro(installment.amount)}</td>
                <td className={CELL}>{installment.payment_method_code ?? ''}</td>
                <td className={CELL}>
                  <InstallmentStatusBadge status={installment.status} />
                </td>
                <td className={NUM_CELL}>{formatEuro(installment.collected_amount)}</td>
                <td className={`${CELL} tabular-nums`}>{formatDate(installment.collected_at)}</td>
                {canCollect ? (
                  <td className="px-2 py-1 text-right">
                    <div className="flex justify-end gap-1">
                      {hasCollection ? (
                        <Button
                          type="button"
                          size="xs"
                          variant="outline"
                          disabled={busyInstallmentId === installment.id}
                          onClick={() => onClear(installment)}
                        >
                          <Undo2 aria-hidden="true" />
                          {t('invoices.detail.clearCollection')}
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
                          {t('invoices.detail.recordCollection')}
                        </Button>
                      )}
                    </div>
                  </td>
                ) : null}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
