import { useTranslation } from 'react-i18next'
import { AlertTriangle, Pencil } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import type { AbsenceBalanceRow } from '@/features/absences/types'
import { cn } from '@/lib/utils'

interface BalanceTileProps {
  row: AbsenceBalanceRow
  dailyMinutes: number
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

/** One absence type: residual up front, taken/accrued bar, then pending, allowance and carry-over (days + hours). */
export function BalanceTile({ row, dailyMinutes, onEdit }: BalanceTileProps) {
  const { t } = useTranslation()
  const format = (minutes: number) => formatAbsenceMinutes(minutes, dailyMinutes, t)
  const isNegative = row.residual_minutes < 0
  const hasAccrued = row.accrued_minutes > 0

  return (
    <li className="flex flex-col gap-2 rounded-lg border border-border bg-background p-3">
      <div className="flex items-center justify-between gap-2">
        <span className="flex min-w-0 items-center gap-1.5 text-sm font-semibold">
          <span aria-hidden="true" className={cn('size-2 shrink-0 rounded-full', absenceTypeDotClass(row.absence_type.color))} />
          <span className="truncate">{row.absence_type.name}</span>
        </span>
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
      <div>
        <p className="text-[11px] text-muted-foreground">{t('absences.balances.columns.residual')}</p>
        <p
          className={cn(
            'flex items-center gap-1 text-lg font-semibold tabular-nums',
            isNegative && 'text-destructive',
          )}
        >
          {isNegative ? (
            <>
              <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
              <span className="sr-only">{t('absences.balances.negative')}</span>
            </>
          ) : null}
          {format(row.residual_minutes)}
        </p>
      </div>
      <div className="flex flex-col gap-1">
        <Progress
          size="sm"
          value={hasAccrued ? row.used_minutes : 0}
          max={hasAccrued ? row.accrued_minutes : 1}
          indicatorClassName={absenceTypeDotClass(row.absence_type.color)}
          aria-label={t('absences.balances.progress', { used: format(row.used_minutes), accrued: format(row.accrued_minutes) })}
        />
        <p className="text-[11px] text-muted-foreground">
          {t('absences.balances.progress', { used: format(row.used_minutes), accrued: format(row.accrued_minutes) })}
        </p>
      </div>
      <dl className="grid grid-cols-3 gap-2">
        <Stat label={t('absences.balances.columns.pending')} value={format(row.pending_minutes)} />
        <Stat label={t('absences.balances.columns.annual')} value={format(row.annual_minutes)} />
        <Stat label={t('absences.balances.columns.carriedOver')} value={format(row.carried_over_minutes)} />
      </dl>
      {row.source === 'default' ? (
        <p className="text-[11px] text-muted-foreground">{t('absences.balances.fromDefault')}</p>
      ) : null}
    </li>
  )
}
