import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES, CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { InstallmentStatusBadge } from '@/features/invoices/invoice-status-badges'
import { INSTALLMENT_STATUSES, type InstallmentStatus } from '@/features/invoices/types'

const OVERDUE_YES = 'yes'

function isInstallmentStatus(value: unknown): value is InstallmentStatus {
  return typeof value === 'string' && (INSTALLMENT_STATUSES as readonly string[]).includes(value)
}

/** Derived installment status (unpaid / partially paid / paid) as the shared badge. */
export function InstallmentStatusCell({ value }: ICellRendererParams) {
  return isInstallmentStatus(value) ? (
    <div className={CELL_WRAPPER}>
      <InstallmentStatusBadge status={value} />
    </div>
  ) : (
    <EmptyCell />
  )
}

/** "Scaduta" badge for an open installment past its due date; nothing otherwise. */
export function OverdueCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  if (value !== OVERDUE_YES && value !== true) {
    return <EmptyCell />
  }
  return (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, BADGE_COLOR_CLASSES.red)}>
        {t('invoiceInstallments.overdue.yes')}
      </Badge>
    </div>
  )
}

/** Days past due, right-aligned; zero (not overdue) renders empty. */
export function DaysOverdueCell({ value }: ICellRendererParams) {
  const days = Number(value)
  return Number.isFinite(days) && days > 0 ? (
    <span className="block w-full text-right font-medium tabular-nums text-destructive">{days}</span>
  ) : (
    <EmptyCell align="left" />
  )
}
