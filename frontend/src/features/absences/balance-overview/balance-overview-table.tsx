import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import type { AbsenceBalanceOverview, AbsenceBalanceOverviewPerson, AbsenceBalanceRow } from '@/features/absences/types'
import { cn } from '@/lib/utils'

const HEAD_CLASS = 'whitespace-nowrap px-2 py-1.5 text-left text-xs font-medium text-muted-foreground'
const CELL_CLASS = 'whitespace-nowrap px-2 py-1.5 text-xs tabular-nums'
const STICKY_CLASS = 'sticky left-0 z-10 bg-card'

interface BalanceCellProps {
  row: AbsenceBalanceRow | undefined
  dailyMinutes: number
}

/** Residual up front; the breakdown is the cell's title and a screen-reader-only line. */
function BalanceCell({ row, dailyMinutes }: BalanceCellProps) {
  const { t } = useTranslation()
  if (!row) {
    return <td className={cn(CELL_CLASS, 'text-muted-foreground')}>-</td>
  }
  const format = (minutes: number) => formatAbsenceMinutes(minutes, dailyMinutes, t)
  const details = t('absences.balances.overview.cellDetails', {
    annual: format(row.annual_minutes),
    accrued: format(row.accrued_minutes),
    used: format(row.used_minutes),
    pending: format(row.pending_minutes),
  })
  const isNegative = row.residual_minutes < 0

  return (
    <td className={cn(CELL_CLASS, isNegative && 'text-destructive')} title={details}>
      <span className="inline-flex items-center gap-1 font-medium">
        {isNegative ? <AlertTriangle className="size-3.5" aria-hidden="true" /> : null}
        {format(row.residual_minutes)}
      </span>
      <span className="sr-only">{details}</span>
    </td>
  )
}

interface BalanceOverviewTableProps {
  overview: AbsenceBalanceOverview
  selected: ReadonlySet<number>
  onToggle: (userId: number) => void
  onTogglePage: (userIds: number[], checked: boolean) => void
}

function pageCheckState(people: AbsenceBalanceOverviewPerson[], selected: ReadonlySet<number>): boolean | 'indeterminate' {
  const count = people.filter((person) => selected.has(person.user.id)).length
  if (count === 0) return false
  return count === people.length ? true : 'indeterminate'
}

/** People x types, residual per cell; scrolls inside its own container so the page never scrolls sideways. */
export function BalanceOverviewTable({ overview, selected, onToggle, onTogglePage }: BalanceOverviewTableProps) {
  const { t } = useTranslation()
  const state = pageCheckState(overview.people, selected)

  return (
    <div className="max-w-full overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full border-collapse">
        <caption className="sr-only">{t('absences.balances.overview.caption', { year: overview.year })}</caption>
        <thead className="border-b border-border bg-surface">
          <tr>
            <th scope="col" className={cn(HEAD_CLASS, STICKY_CLASS, 'bg-surface')}>
              <span className="flex items-center gap-2">
                <Checkbox
                  checked={state}
                  aria-label={t('absences.balances.overview.selectAll')}
                  onCheckedChange={(checked) =>
                    onTogglePage(
                      overview.people.map((person) => person.user.id),
                      checked === true,
                    )
                  }
                />
                {t('absences.balances.person.label')}
              </span>
            </th>
            {overview.types.map((type) => (
              <th key={type.id} scope="col" className={HEAD_CLASS}>
                <span className="flex items-center gap-1.5">
                  <span aria-hidden="true" className={cn('size-2 rounded-full', absenceTypeDotClass(type.color))} />
                  {type.name}
                </span>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {overview.people.map((person) => (
            <tr key={person.user.id} className="border-b border-border last:border-b-0 hover:bg-muted/40">
              <th scope="row" className={cn(CELL_CLASS, STICKY_CLASS, 'text-left font-medium')}>
                <span className="flex items-center gap-2">
                  <Checkbox
                    checked={selected.has(person.user.id)}
                    aria-label={t('absences.balances.overview.selectPerson', { name: person.user.name })}
                    onCheckedChange={() => onToggle(person.user.id)}
                  />
                  <span className="max-w-48 truncate">{person.user.name}</span>
                </span>
              </th>
              {overview.types.map((type) => (
                <BalanceCell
                  key={type.id}
                  row={person.balances.find((balance) => balance.absence_type.id === type.id)}
                  dailyMinutes={person.daily_minutes}
                />
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
