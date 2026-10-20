import { useTranslation } from 'react-i18next'
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { AbsenceCalendarGrid } from '@/features/absences/calendar/absence-calendar-grid'
import { CALENDAR_STATUS_CLASS, CALENDAR_STATUS_ICON } from '@/features/absences/calendar/calendar-status'
import { dayOf, formatMonthLabel, monthOf } from '@/features/absences/calendar/calendar-month'
import { useAbsenceCalendar } from '@/features/absences/calendar/use-absence-calendar'
import { cn } from '@/lib/utils'

const NAV_BUTTON_CLASS = 'h-8 rounded-sm px-2 text-xs text-muted-foreground hover:bg-accent hover:text-foreground'
const LEGEND_STATUSES = ['approved', 'submitted'] as const

interface AbsenceCalendarProps {
  onOpen: (requestId: number) => void
}

/**
 * The "Calendario" tab of the Assenze page (spec 0279): month navigation,
 * function filter (when the actor sees more than themselves), the grid and
 * its legend.
 */
export function AbsenceCalendar({ onOpen }: AbsenceCalendarProps) {
  const { t, i18n } = useTranslation()
  const { month, showPrevious, showNext, showCurrent, businessFunctionId, setBusinessFunctionId, query } =
    useAbsenceCalendar()
  const now = new Date()
  const calendar = query.data

  return (
    <section className="flex flex-col gap-3" aria-label={t('absences.calendar.caption')}>
      <div className="flex flex-wrap items-center gap-2">
        <div className="inline-flex items-center rounded-md border border-field-border bg-field p-1">
          <Button type="button" variant="ghost" aria-label={t('absences.calendar.previous')} onClick={showPrevious} className={NAV_BUTTON_CLASS}>
            <ChevronLeft className="size-3.5" aria-hidden="true" />
          </Button>
          <Button type="button" variant="ghost" aria-label={t('absences.calendar.next')} onClick={showNext} className={NAV_BUTTON_CLASS}>
            <ChevronRight className="size-3.5" aria-hidden="true" />
          </Button>
        </div>
        <div className="inline-flex h-10 items-center gap-2 rounded-md border border-field-border bg-field px-3">
          <CalendarDays className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
          <span className="text-xs font-semibold capitalize" aria-live="polite">
            {formatMonthLabel(month, i18n.language)}
          </span>
        </div>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          disabled={month === monthOf(now)}
          onClick={showCurrent}
        >
          {t('absences.calendar.currentMonth')}
        </Button>
        {calendar && calendar.scope !== 'own' ? (
          <div className="w-full sm:w-64">
            <AsyncPaginatedSelect
              resource={BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE}
              value={businessFunctionId}
              onChange={setBusinessFunctionId}
              labels={{
                placeholder: t('absences.calendar.functionPlaceholder'),
                searchPlaceholder: t('absences.calendar.functionSearch'),
                empty: t('absences.calendar.functionEmpty'),
                error: t('absences.calendar.functionError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('absences.calendar.functionFilter'),
                retry: t('common.retry'),
              }}
            />
          </div>
        ) : null}
      </div>

      <ul className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
        {LEGEND_STATUSES.map((status) => {
          const Icon = CALENDAR_STATUS_ICON[status]
          return (
            <li key={status} className="inline-flex items-center gap-1.5">
              <span className={cn('flex size-5 items-center justify-center rounded-sm', CALENDAR_STATUS_CLASS[status])}>
                <Icon className="size-3" aria-hidden="true" />
              </span>
              {t(`absences.status.${status}`)}
            </li>
          )
        })}
        <li className="inline-flex items-center gap-1.5">
          <span className="size-5 rounded-sm border border-border bg-muted/40" aria-hidden="true" />
          {t('absences.calendar.nonWorkingDay')}
        </li>
      </ul>

      {query.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('absences.calendar.error')}
        </p>
      ) : calendar ? (
        calendar.people.length > 0 ? (
          <AbsenceCalendarGrid calendar={calendar} today={dayOf(now)} onOpen={onOpen} />
        ) : (
          <p className="text-sm text-muted-foreground">{t('absences.calendar.empty')}</p>
        )
      ) : (
        <Skeleton className="h-40 w-full" />
      )}
    </section>
  )
}
