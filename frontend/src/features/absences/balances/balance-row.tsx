import { useTranslation } from 'react-i18next'
import { AlertTriangle, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { formatSignedMinutes } from '@/features/absences/balances/balance-format'
import type { AbsenceBalanceRow } from '@/features/absences/types'

interface BalanceRowProps {
  row: AbsenceBalanceRow
  onEdit?: (row: AbsenceBalanceRow) => void
}

interface StatProps {
  label: string
  value: string
}

function Stat({ label, value }: StatProps) {
  return (
    <div className="min-w-0">
      <dt className="truncate text-[11px] text-muted-foreground">{label}</dt>
      <dd className="truncate text-xs font-medium tabular-nums">{value}</dd>
    </div>
  )
}

/** One absence type: allowance, carry-over, accrued, used, pending and residual hours. */
export function BalanceRow({ row, onEdit }: BalanceRowProps) {
  const { t } = useTranslation()
  const isNegative = row.residual_minutes < 0

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border bg-background p-3">
      <div className="flex items-center justify-between gap-2">
        <span className="min-w-0 truncate text-sm font-semibold">{row.absence_type.name}</span>
        {onEdit ? (
          <Button
            type="button"
            size="sm"
            variant="secondary"
            aria-label={`${t('absences.balances.editAllowance')} ${row.absence_type.name}`}
            onClick={() => onEdit(row)}
          >
            <Pencil className="size-3.5" aria-hidden="true" />
            {t('absences.balances.editAllowance')}
          </Button>
        ) : null}
      </div>
      <dl className="grid grid-cols-3 gap-2 sm:grid-cols-6">
        <Stat label={t('absences.balances.columns.annual')} value={formatSignedMinutes(row.annual_minutes)} />
        <Stat label={t('absences.balances.columns.carriedOver')} value={formatSignedMinutes(row.carried_over_minutes)} />
        <Stat label={t('absences.balances.columns.accrued')} value={formatSignedMinutes(row.accrued_minutes)} />
        <Stat label={t('absences.balances.columns.used')} value={formatSignedMinutes(row.used_minutes)} />
        <Stat label={t('absences.balances.columns.pending')} value={formatSignedMinutes(row.pending_minutes)} />
        <div className="min-w-0">
          <dt className="truncate text-[11px] text-muted-foreground">{t('absences.balances.columns.residual')}</dt>
          <dd
            className={
              isNegative
                ? 'flex items-center gap-1 text-xs font-semibold tabular-nums text-destructive'
                : 'text-xs font-semibold tabular-nums'
            }
          >
            {isNegative ? (
              <>
                <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
                <span className="sr-only">{t('absences.balances.negative')}</span>
              </>
            ) : null}
            {formatSignedMinutes(row.residual_minutes)}
          </dd>
        </div>
      </dl>
    </li>
  )
}
