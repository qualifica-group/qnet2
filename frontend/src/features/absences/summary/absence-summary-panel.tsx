import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { AbsenceUpcomingItem } from '@/features/absences/summary/absence-upcoming-item'
import { useAbsenceSummary } from '@/features/absences/summary/use-absence-summary'

interface AbsenceSummaryPanelProps {
  onOpen: (id: number) => void
  /** Moves the grid to the "To approve" tab. */
  onGoToApprove: () => void
}

/**
 * Personal summary on top of the Requests view (spec 0304 D-18): the banner of
 * the requests the actor has to decide, the next absences and the pending
 * counter. Silent while loading or on error: the balances and the grid below
 * stay usable without it.
 */
export function AbsenceSummaryPanel({ onOpen, onGoToApprove }: AbsenceSummaryPanelProps) {
  const { t } = useTranslation()
  const { data } = useAbsenceSummary()
  if (!data) return null

  return (
    <section aria-label={t('absences.summary.title')} className="flex flex-col gap-3">
      {data.to_approve_count > 0 ? (
        <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card px-3 py-2 text-sm shadow-sm">
          <p role="status">{t('absences.summary.toApproveBanner', { count: data.to_approve_count })}</p>
          <Button type="button" size="sm" variant="secondary" onClick={onGoToApprove}>
            {t('absences.summary.toApproveLink')}
          </Button>
        </div>
      ) : null}

      <div className="grid gap-3 rounded-lg border border-border bg-card p-3 shadow-sm md:grid-cols-[1fr_auto]">
        <div className="min-w-0">
          <h2 className="mb-1 text-base font-semibold">{t('absences.summary.upcomingTitle')}</h2>
          {data.my_upcoming.length > 0 ? (
            <ul className="flex flex-col">
              {data.my_upcoming.map((absence) => (
                <AbsenceUpcomingItem
                  key={absence.request_id}
                  absence={absence}
                  dailyMinutes={data.daily_minutes}
                  onOpen={onOpen}
                />
              ))}
            </ul>
          ) : (
            <p className="text-sm text-muted-foreground">{t('absences.summary.upcomingEmpty')}</p>
          )}
        </div>
        <div className="md:min-w-48 md:border-l md:border-border md:pl-3">
          <h2 className="mb-1 text-base font-semibold">{t('absences.summary.pendingTitle')}</h2>
          <p className="text-sm text-muted-foreground">
            {data.my_pending_count > 0
              ? t('absences.summary.pendingCount', { count: data.my_pending_count })
              : t('absences.summary.pendingEmpty')}
          </p>
        </div>
      </div>
    </section>
  )
}
