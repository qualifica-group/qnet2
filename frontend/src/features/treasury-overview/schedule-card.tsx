import { useTranslation } from 'react-i18next'
import { OverviewCard } from '@/features/treasury-overview/overview-card'
import { installmentQuickLink } from '@/features/treasury-overview/overview-links'
import { OverviewFigure } from '@/features/treasury-overview/overview-figure'
import type { OverdueBucket, ScheduleSide } from '@/features/treasury-overview/types'

const BUCKETS: OverdueBucket[] = ['overdue_1_30', 'overdue_31_60', 'overdue_61_90', 'overdue_over_90']

interface ScheduleCardProps {
  side: 'receivable' | 'payable'
  data: ScheduleSide
  to: string
}

/** Open receivables or payables: total, not yet due, overdue by bucket. */
export function ScheduleCard({ side, data, to }: ScheduleCardProps) {
  const { t } = useTranslation()
  // The buckets have no URL filter of their own: they open the whole overdue list (spec 0363).
  const overdueTo = installmentQuickLink(to, 'overdue')

  return (
    <OverviewCard title={t(`treasuryOverview.${side}.title`)}>
      <OverviewFigure
        label={t('treasuryOverview.schedule.open')}
        amount={data.open_total}
        to={installmentQuickLink(to, 'unpaid')}
        strong
      />
      <OverviewFigure
        label={t('treasuryOverview.schedule.notDue')}
        amount={data.not_due_total}
        to={installmentQuickLink(to, 'due')}
      />
      <OverviewFigure label={t('treasuryOverview.schedule.overdue')} amount={data.overdue_total} to={overdueTo} />
      <ul className="flex flex-col gap-1 border-t pt-2" aria-label={t('treasuryOverview.schedule.buckets')}>
        {BUCKETS.map((bucket) => (
          <li key={bucket}>
            <OverviewFigure label={t(`treasuryOverview.buckets.${bucket}`)} amount={data.overdue[bucket]} to={overdueTo} />
          </li>
        ))}
      </ul>
    </OverviewCard>
  )
}
