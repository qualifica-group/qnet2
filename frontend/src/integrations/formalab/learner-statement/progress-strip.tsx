import { useTranslation } from 'react-i18next'
import { euro } from '@/integrations/formalab/learner-statement/format'
import type { LearnerStatement } from '@/integrations/formalab/learner-statement/types'

/** One chart token per month, cycling (the legacy palette of 20 colours, on the theme). */
const SEGMENT_CLASSES = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3', 'bg-chart-4', 'bg-chart-5']

const MAX_PERCENT = 100

/** "Avanzamento Commessa": the progress of each month and its share of the learner service revenue. */
export function ProgressStrip({ progress }: { progress: LearnerStatement['progress'] }) {
  const { t, i18n } = useTranslation('formalab')
  const monthName = (year: number, month: number) =>
    new Intl.DateTimeFormat(i18n.language, { month: 'long', year: 'numeric' }).format(new Date(year, month - 1, 1))

  return (
    <section aria-labelledby="learner-statement-progress" className="flex flex-col gap-2">
      <h3 id="learner-statement-progress" className="text-sm font-semibold">
        {t('learnerStatement.progress.title')}
      </h3>
      {progress.items.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('learnerStatement.progress.empty')}</p>
      ) : (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
          <ol className="flex min-w-0 flex-1 overflow-hidden rounded-md bg-muted">
            {progress.items.map((item, index) => (
              <li
                key={`${item.year}-${item.month}`}
                className="flex min-w-16 flex-col items-center gap-0.5 py-1 text-center text-[11px]"
                style={{ width: `${Math.min(item.percentage, MAX_PERCENT)}%` }}
              >
                <span className="font-semibold">{`${Math.round(item.percentage)}%`}</span>
                <span className="truncate text-muted-foreground">{monthName(item.year, item.month)}</span>
                <span className="font-semibold tabular-nums">{euro(item.revenue)}</span>
                <span className={`h-2 w-full ${SEGMENT_CLASSES[index % SEGMENT_CLASSES.length]}`} aria-hidden="true" />
              </li>
            ))}
          </ol>
          <p className="shrink-0 text-xs font-semibold">
            {t('learnerStatement.progress.total', { percentage: Math.round(progress.total_percentage), amount: euro(progress.total_revenue) })}
          </p>
        </div>
      )}
    </section>
  )
}
