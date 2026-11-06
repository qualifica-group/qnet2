import { useTranslation } from 'react-i18next'
import { Repeat } from 'lucide-react'
import { formatDate } from '@/lib/formatting/date-display'
import { formatTaskRecurrenceRule } from '@/features/tasks/task-recurrence-format'
import { recurrenceToRule } from '@/features/outbound-emails/outbound-email-recurrence-schema'
import type { OutboundEmailRecurrence } from '@/features/outbound-emails/types'

/**
 * Read-only recap of a scheduled series in the detail (spec 0362, AC-023):
 * the rule as a sentence, its period, the next send, how many copies went
 * out and, once the system stopped it, why.
 */
export function OutboundEmailRecurrenceSummary({ recurrence }: { recurrence: OutboundEmailRecurrence }) {
  const { t, i18n } = useTranslation()

  const rows: { label: string; value: string }[] = [
    {
      label: t('outboundEmails.recurrence.period'),
      value: t('outboundEmails.recurrence.periodValue', {
        from: formatDate(recurrence.starts_on),
        to: formatDate(recurrence.ends_on),
      }),
    },
    ...(recurrence.next_send_on
      ? [{ label: t('outboundEmails.recurrence.nextSend'), value: formatDate(recurrence.next_send_on) }]
      : []),
    { label: t('outboundEmails.recurrence.sentCount'), value: String(recurrence.sent_count) },
    ...(recurrence.end_reason
      ? [{ label: t('outboundEmails.recurrence.endReason'), value: t(`outboundEmails.recurrence.endReasons.${recurrence.end_reason}`) }]
      : []),
  ]

  return (
    <section
      aria-label={t('outboundEmails.recurrence.title')}
      className="flex flex-col gap-2 rounded-lg border bg-card p-3 shadow-xs"
    >
      <h4 className="flex items-center gap-1.5 text-xs font-semibold">
        <Repeat className="size-3.5 text-muted-foreground" aria-hidden="true" />
        {t('outboundEmails.recurrence.title')}
      </h4>
      <p className="text-sm font-medium break-words">
        {formatTaskRecurrenceRule(recurrenceToRule(recurrence), t, i18n.language)}
      </p>
      <dl className="grid grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-3 gap-y-1 border-t pt-2 text-xs">
        {rows.map((row) => (
          <div key={row.label} className="contents">
            <dt className="text-muted-foreground">{row.label}</dt>
            <dd className="font-medium">{row.value}</dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
