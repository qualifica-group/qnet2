import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { euro } from '@/integrations/formalab/learner-statement/format'
import { summaryRows, type SummaryRow, type SummaryTone } from '@/integrations/formalab/learner-statement/summary-rows'
import type { LearnerStatement } from '@/integrations/formalab/learner-statement/types'

/** Header tint and accent per scenario: always a token, never a raw colour. */
const HEADER_CLASS: Record<SummaryTone, string> = {
  neutral: 'bg-muted',
  economic: 'bg-success/15 border-l-success',
  advance: 'bg-warning/20 border-l-warning',
  forecastBalance: 'bg-chart-2/15 border-l-chart-2',
  balance: 'bg-primary/10 border-l-primary',
  forecastTotal: 'bg-success/30 border-l-success',
  actualTotal: 'bg-primary/25 border-l-primary',
}

const FILL_CLASS: Record<SummaryTone | 'mol' | 'total', string> = {
  ...HEADER_CLASS,
  mol: 'bg-accent',
  total: 'bg-muted',
}

/** "Riepilogo Costi e Ricavi": the costs and the scenario blocks (spec 0427). */
export function SummaryPanel({ statement }: { statement: LearnerStatement }) {
  const { t } = useTranslation('formalab')
  const rows = summaryRows(statement, t)

  return (
    <section aria-labelledby="learner-statement-summary" className="flex min-w-0 flex-col gap-2">
      <h3 id="learner-statement-summary" className="text-sm font-semibold">
        {t('learnerStatement.summary.title')}
      </h3>
      <div className="overflow-hidden rounded-lg border bg-card">
        <table className="w-full text-xs">
          <tbody>
            {rows.map((row, index) => (
              <SummaryTableRow key={index} row={row} />
            ))}
          </tbody>
        </table>
      </div>
      {statement.payment_state === 'none' ? (
        <p className="text-center text-xs italic text-muted-foreground">{t('learnerStatement.summary.noPayments')}</p>
      ) : null}
    </section>
  )
}

function SummaryTableRow({ row }: { row: SummaryRow }) {
  if (row.kind === 'header') {
    return (
      <tr className={cn('border-l-2 border-t', HEADER_CLASS[row.tone])}>
        <th colSpan={2} scope="colgroup" className="px-2 py-1.5 text-center font-semibold">
          <span>{row.title}</span>
          {row.badge ? (
            <Badge variant="secondary" className="ml-1 text-[10px]">
              {row.badge}
            </Badge>
          ) : null}
          {row.subtitle ? <span className="block text-[11px] font-normal text-muted-foreground">{row.subtitle}</span> : null}
        </th>
      </tr>
    )
  }

  const negative = row.signed === true && row.value < 0

  return (
    <tr className={cn('border-t', row.fill ? FILL_CLASS[row.fill] : undefined)}>
      <th scope="row" className={cn('px-2 py-1 text-left font-normal', row.strong && 'font-semibold', row.muted && 'text-muted-foreground')}>
        {row.label}
      </th>
      <td className={cn('px-2 py-1 text-right tabular-nums', row.strong && 'font-semibold', row.muted && 'text-muted-foreground', negative && 'text-destructive')}>
        {euro(row.value)}
        {row.notes?.map((note) => (
          <span key={note} className="block text-[11px] font-normal italic text-muted-foreground">
            {note}
          </span>
        ))}
      </td>
    </tr>
  )
}
