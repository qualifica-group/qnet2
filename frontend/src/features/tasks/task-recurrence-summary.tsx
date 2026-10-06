import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { BriefcaseBusiness, CalendarCheck, Hash, Infinity as InfinityIcon, Repeat } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'
import { formatTaskRecurrenceRule, weekdayInitial } from '@/features/tasks/task-recurrence-format'
import { WEEKDAY_ORDER } from '@/features/tasks/task-recurrence-weekdays'
import type { TaskRecurrenceDetail } from '@/features/tasks/types'

/** A small fact under the rule sentence; `bg-card` lifts it off the tile's tint veil. */
function RecurrenceChip({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-md border bg-card px-2 py-0.5 text-xs text-muted-foreground [&>svg]:size-3.5">
      {icon}
      {children}
    </span>
  )
}

/**
 * The week at a glance for a weekly rule: picked days filled, the others
 * outlined (fill, not colour alone). Decorative: the sentence above already
 * names the days in words, so assistive tech skips the strip.
 */
function WeekdayStrip({ weekdays, language }: { weekdays: number[]; language: string }) {
  return (
    <div className="flex items-center gap-1" aria-hidden="true">
      {WEEKDAY_ORDER.map((day) => (
        <span
          key={day}
          className={cn(
            'flex size-6 items-center justify-center rounded-full text-xs font-medium',
            weekdays.includes(day)
              ? 'bg-primary text-primary-foreground shadow-sm'
              : 'border bg-card text-muted-foreground',
          )}
        >
          {weekdayInitial(day, language)}
        </span>
      ))}
    </div>
  )
}

function EndChip({ rule }: { rule: TaskRecurrenceDetail }) {
  const { t } = useTranslation()

  if (rule.ends === 'on_date') {
    return (
      <RecurrenceChip icon={<CalendarCheck aria-hidden="true" />}>
        {t('tasks.detail.recurrenceCard.endsOn', { date: formatDate(rule.ends_on) })}
      </RecurrenceChip>
    )
  }
  if (rule.ends === 'after_count') {
    return (
      <RecurrenceChip icon={<Hash aria-hidden="true" />}>
        {t('tasks.detail.recurrenceCard.endsAfter', { count: rule.occurrence_count ?? 0 })}
      </RecurrenceChip>
    )
  }
  return (
    <RecurrenceChip icon={<InfinityIcon aria-hidden="true" />}>{t('tasks.detail.recurrenceCard.endsNever')}</RecurrenceChip>
  )
}

/**
 * The read value of the detail's "Ricorrenza" (spec 0195): a tile instead of
 * a bare sentence. Off, a dashed placeholder; on, a veil of the brand colour
 * (a tint over the card, not a new surface) with the frequency as eyebrow,
 * the rule sentence (`formatTaskRecurrenceRule`, the same the header badge
 * shows), the week strip for a weekly rule and the end/workday facts.
 */
export function TaskRecurrenceSummary({ rule }: { rule: TaskRecurrenceDetail | null }) {
  const { t, i18n } = useTranslation()

  if (!rule) {
    return (
      <div className="flex items-center gap-3 rounded-lg border border-dashed p-3">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground">
          <Repeat className="size-4" aria-hidden="true" />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-medium text-foreground">{t('tasks.form.summary.recurrenceOff')}</p>
          <p className="text-xs text-muted-foreground">{t('tasks.detail.recurrenceCard.offHint')}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-3 rounded-lg border border-primary/20 bg-primary/5 p-3">
      <div className="flex items-start gap-3">
        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-sm">
          <Repeat className="size-4" aria-hidden="true" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <span className="text-xs font-semibold tracking-wide text-primary uppercase">
            {t(`tasks.form.recurrence.frequencyOption.${rule.frequency}`)}
          </span>
          <p className="text-sm font-medium text-foreground">{formatTaskRecurrenceRule(rule, t, i18n.language)}</p>
        </div>
      </div>

      {rule.frequency === 'weekly' && rule.weekdays ? (
        <WeekdayStrip weekdays={rule.weekdays} language={i18n.language} />
      ) : null}

      <div className="flex flex-wrap items-center gap-1.5">
        <EndChip rule={rule} />
        {rule.workdays_only ? (
          <RecurrenceChip icon={<BriefcaseBusiness aria-hidden="true" />}>
            {t('tasks.form.recurrence.workdaysOnly')}
          </RecurrenceChip>
        ) : null}
      </div>
    </div>
  )
}
