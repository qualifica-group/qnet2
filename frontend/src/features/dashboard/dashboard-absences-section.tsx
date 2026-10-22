import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { CalendarOff } from 'lucide-react'
import { Skeleton } from '@/components/ui/skeleton'
import { formatAbsencePeriod } from '@/features/absences/requests/absence-request-format'
import { AbsenceTypeChip } from '@/features/absences/summary/absence-type-chip'
import { useAbsenceSummary } from '@/features/absences/summary/use-absence-summary'
import type { AbsenceDayPart } from '@/features/absences/types'
import { formatDate } from '@/lib/formatting/date-display'
import { DashboardSectionHeader } from '@/features/dashboard/dashboard-section-header'

const ABSENCES_PATH = '/absences'
const PANEL_CLASS = 'flex flex-col gap-1 rounded-lg border border-border bg-card p-3 shadow-sm'

function useDayPartLabel() {
  const { t } = useTranslation()
  return (dayPart: AbsenceDayPart | null) =>
    dayPart ? t(`absences.requests.form.dayParts.${dayPart}`) : null
}

/**
 * Absences block of the dashboard (spec 0304 D-18, AC-025): requests to approve
 * with a link to the page, who is out today and the actor's next absences.
 * Mounted only with `absence-requests.viewAny`.
 */
export function DashboardAbsencesSection() {
  const { t } = useTranslation()
  const dayPartLabel = useDayPartLabel()
  const { data, isError } = useAbsenceSummary()

  return (
    <section aria-labelledby="dashboard-absences-heading" className="flex flex-col gap-3">
      <DashboardSectionHeader
        id="dashboard-absences-heading"
        icon={<CalendarOff aria-hidden="true" />}
        title={t('dashboard.absencesSection.title')}
      />

      {isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('dashboard.absencesSection.error')}
        </p>
      ) : !data ? (
        <Skeleton className="h-24 w-full" />
      ) : (
        <div className="grid gap-3 md:grid-cols-3">
          <div className={PANEL_CLASS}>
            <h3 className="text-sm font-semibold">{t('dashboard.absencesSection.toApprove')}</h3>
            {data.to_approve_count > 0 ? (
              <>
                <p className="text-2xl font-semibold tabular-nums">{data.to_approve_count}</p>
                <Link to={ABSENCES_PATH} className="text-xs font-medium text-primary underline-offset-2 hover:underline">
                  {t('dashboard.absencesSection.toApproveLink')}
                </Link>
              </>
            ) : (
              <p className="text-sm text-muted-foreground">{t('dashboard.absencesSection.toApproveEmpty')}</p>
            )}
          </div>

          <div className={PANEL_CLASS}>
            <h3 className="text-sm font-semibold">{t('dashboard.absencesSection.absentToday')}</h3>
            {data.absent_today.length > 0 ? (
              <ul className="flex flex-col gap-1.5">
                {data.absent_today.map((absent) => {
                  const part = dayPartLabel(absent.day_part)
                  return (
                    <li key={`${absent.request_id}-${absent.user.id}`} className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                      <span className="font-medium">{absent.user.name}</span>
                      <AbsenceTypeChip name={absent.absence_type_name} color={absent.color} />
                      {part ? <span className="text-muted-foreground">{part}</span> : null}
                      <span className="text-muted-foreground">{t('dashboard.absencesSection.until', { date: formatDate(absent.ends_on) })}</span>
                    </li>
                  )
                })}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">{t('dashboard.absencesSection.absentTodayEmpty')}</p>
            )}
          </div>

          <div className={PANEL_CLASS}>
            <h3 className="text-sm font-semibold">{t('dashboard.absencesSection.myUpcoming')}</h3>
            {data.my_upcoming.length > 0 ? (
              <ul className="flex flex-col gap-1.5">
                {data.my_upcoming.map((absence) => {
                  const part = dayPartLabel(absence.day_part)
                  return (
                    <li key={absence.request_id} className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                      <AbsenceTypeChip name={absence.absence_type.name} color={absence.absence_type.color} />
                      <Link to={`${ABSENCES_PATH}?request=${absence.request_id}`} className="tabular-nums hover:underline">
                        {formatAbsencePeriod(absence)}
                      </Link>
                      {part ? <span className="text-muted-foreground">{part}</span> : null}
                    </li>
                  )
                })}
              </ul>
            ) : (
              <p className="text-sm text-muted-foreground">{t('dashboard.absencesSection.myUpcomingEmpty')}</p>
            )}
          </div>
        </div>
      )}
    </section>
  )
}
