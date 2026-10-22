import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { TriangleAlert } from 'lucide-react'
import { absenceTypeDotClass } from '@/features/absences/absence-type-color'
import { formatAbsenceMinutes } from '@/features/absences/absence-duration-format'
import { formatAbsencePeriod } from '@/features/absences/requests/absence-request-format'
import type { AbsenceRequestInsight } from '@/features/absences/types'
import { formatDate } from '@/lib/formatting/date-display'
import { cn } from '@/lib/utils'

interface AbsenceInsightPanelProps {
  insight?: AbsenceRequestInsight
  isPending: boolean
  isError: boolean
  /** `preview` = the request being filled; `context` = the decision on a sent request. */
  variant: 'preview' | 'context'
}

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex items-baseline justify-between gap-3">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="text-right font-medium">{children}</dd>
    </div>
  )
}

function Warning({ children }: { children: ReactNode }) {
  return (
    <p className="flex items-start gap-1.5 text-amber-700 dark:text-amber-400">
      <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
      <span>{children}</span>
    </p>
  )
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="space-y-1 border-t border-border pt-2">
      <h4 className="font-medium">{title}</h4>
      {children}
    </section>
  )
}

function BalanceRows({ balance, dailyMinutes }: { balance: NonNullable<AbsenceRequestInsight['balance']>; dailyMinutes: number }) {
  const { t } = useTranslation()
  const isNegative = balance.residual_after_minutes < 0

  return (
    <>
      <Row label={t('absences.insight.balanceCurrent', { year: balance.year })}>
        {formatAbsenceMinutes(balance.residual_minutes, dailyMinutes, t)}
      </Row>
      <Row label={t('absences.insight.balanceAfter')}>
        <span className={cn(isNegative && 'text-destructive')}>
          {formatAbsenceMinutes(balance.residual_after_minutes, dailyMinutes, t)}
        </span>
        {isNegative ? <span className="ml-1 text-destructive">({t('absences.insight.balanceNegative')})</span> : null}
      </Row>
    </>
  )
}

function InsightBody({ insight, variant }: { insight: AbsenceRequestInsight; variant: AbsenceInsightPanelProps['variant'] }) {
  const { t } = useTranslation()
  const { notice, overlaps, colleagues_absent: colleagues } = insight

  return (
    <div className="space-y-2">
      <dl className="space-y-0.5">
        {variant === 'context' ? <Row label={t('absences.insight.person')}>{insight.person.name}</Row> : null}
        <Row label={t('absences.insight.workingDays')}>{insight.working_days}</Row>
        <Row label={t('absences.insight.duration')}>
          {formatAbsenceMinutes(insight.minutes, insight.daily_minutes, t)}
        </Row>
        {insight.balance ? <BalanceRows balance={insight.balance} dailyMinutes={insight.daily_minutes} /> : null}
      </dl>
      {insight.balance === null ? <p className="text-muted-foreground">{t('absences.insight.balanceNone')}</p> : null}

      {notice.is_short_notice ? (
        <Warning>{t('absences.insight.shortNotice', { count: notice.min_notice_days ?? 0 })}</Warning>
      ) : null}

      {overlaps.length > 0 ? (
        <Section title={t('absences.insight.overlapsTitle')}>
          <Warning>{t('absences.insight.overlapBlocked')}</Warning>
          <ul className="space-y-0.5">
            {overlaps.map((overlap) => (
              <li key={overlap.request_id}>
                {t('absences.insight.overlap', {
                  type: overlap.absence_type_name,
                  period: formatAbsencePeriod(overlap),
                  status: t(`absences.status.${overlap.status}`),
                })}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      {insight.non_working_days.length > 0 ? (
        <Section title={t('absences.insight.nonWorkingDays')}>
          <ul className="space-y-0.5">
            {insight.non_working_days.map((day) => (
              <li key={day.date}>
                {formatDate(day.date)}: {t(`absences.insight.reasons.${day.reason}`)}
                {day.name ? ` (${day.name})` : ''}
              </li>
            ))}
          </ul>
        </Section>
      ) : null}

      <Section title={t('absences.insight.colleaguesTitle')}>
        {colleagues.length === 0 ? (
          <p className="text-muted-foreground">{t('absences.insight.colleaguesEmpty')}</p>
        ) : (
          <ul className="space-y-0.5">
            {colleagues.map((colleague) => (
              <li key={`${colleague.request_id}-${colleague.user.id}`} className="flex items-start gap-1.5">
                <span
                  aria-hidden="true"
                  className={cn('mt-1 size-2 shrink-0 rounded-full', absenceTypeDotClass(colleague.color))}
                />
                <span className="min-w-0 break-words">
                  {t('absences.insight.colleague', {
                    name: colleague.user.name,
                    type: colleague.absence_type_name,
                    period: formatAbsencePeriod(colleague),
                  })}
                  {colleague.day_part ? `, ${t(`absences.requests.form.dayParts.${colleague.day_part}`)}` : ''}
                  {' - '}
                  {t(`absences.status.${colleague.status}`)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Section>
    </div>
  )
}

/** What a request amounts to: duration, balance, notice, own overlaps and absent colleagues (spec 0304 D-16). */
export function AbsenceInsightPanel({ insight, isPending, isError, variant }: AbsenceInsightPanelProps) {
  const { t } = useTranslation()
  const title = t(variant === 'preview' ? 'absences.insight.previewTitle' : 'absences.insight.contextTitle')

  return (
    <section aria-label={title} className="space-y-2 rounded-lg border border-border bg-muted/40 p-3 text-xs">
      <h3 className="text-sm font-semibold">{title}</h3>
      {isError ? (
        <p role="alert" className="text-destructive">
          {t('absences.insight.error')}
        </p>
      ) : null}
      {insight ? (
        <InsightBody insight={insight} variant={variant} />
      ) : isPending ? (
        <p role="status" className="text-muted-foreground">
          {t('absences.insight.loading')}
        </p>
      ) : isError ? null : (
        <p className="text-muted-foreground">{t('absences.insight.previewEmpty')}</p>
      )}
    </section>
  )
}
