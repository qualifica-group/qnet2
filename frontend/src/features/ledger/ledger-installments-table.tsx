import { useTranslation } from 'react-i18next'
import { formatDate } from '@/lib/formatting/date-display'
import { formatEuro } from '@/features/invoices/invoice-format'
import type { LedgerInstallment, LedgerSide } from '@/features/ledger/types'

const HEAD_CELL = 'px-2 py-1 text-left text-xs font-medium text-muted-foreground'
const NUMERIC_HEAD_CELL = 'px-2 py-1 text-right text-xs font-medium text-muted-foreground'
const CELL = 'px-2 py-1 text-xs'
const NUMERIC_CELL = 'px-2 py-1 text-right text-xs tabular-nums'

interface LedgerInstallmentsTableProps {
  installments: LedgerInstallment[]
  side: LedgerSide
}

/** The installments of one ledger document: due date, settlement (collected/paid) and delay. */
export function LedgerInstallmentsTable({ installments, side }: LedgerInstallmentsTableProps) {
  const { t } = useTranslation()
  const suffix = side === 'customer' ? 'Customer' : 'Supplier'

  if (installments.length === 0) {
    return <p className="px-2 py-1 text-xs text-muted-foreground">{t('ledger.table.noInstallments')}</p>
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[32rem] border-collapse">
        <thead>
          <tr>
            <th scope="col" className={HEAD_CELL}>{t('ledger.installments.sequence')}</th>
            <th scope="col" className={HEAD_CELL}>{t('ledger.installments.dueDate')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.installments.amount')}</th>
            <th scope="col" className={HEAD_CELL}>{t(`ledger.installments.settledAt${suffix}`)}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t(`ledger.installments.settledAmount${suffix}`)}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.installments.residual')}</th>
            <th scope="col" className={NUMERIC_HEAD_CELL}>{t('ledger.installments.daysLate')}</th>
          </tr>
        </thead>
        <tbody>
          {installments.map((installment) => (
            <tr key={installment.id} className="border-t">
              <td className={CELL}>{installment.sequence}</td>
              <td className={CELL}>{formatDate(installment.due_date)}</td>
              <td className={NUMERIC_CELL}>{formatEuro(installment.amount)}</td>
              <td className={CELL}>{installment.settled_at ? formatDate(installment.settled_at) : '-'}</td>
              <td className={NUMERIC_CELL}>
                {installment.settled_amount === null ? '-' : formatEuro(installment.settled_amount)}
              </td>
              <td className={NUMERIC_CELL}>{formatEuro(installment.residual_amount)}</td>
              <td className={NUMERIC_CELL}>{installment.days_late}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
