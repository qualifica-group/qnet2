import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'
import type { FormalabLink } from '@/integrations/formalab/types'

function Field({ label, value }: { label: string; value: string | number | null }) {
  return (
    <div className="flex min-w-0 flex-col">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="truncate text-sm">{value ?? '-'}</dd>
    </div>
  )
}

/** Read-only data of the linked class, plus the last sync outcome. */
export function ClassCard({ link }: { link: FormalabLink }) {
  const { t } = useTranslation('formalab')
  const klass = link.class
  const site = [klass?.site_name, klass?.site_city].filter(Boolean).join(' - ')

  return (
    <div className="flex flex-col gap-3">
      <p className="text-sm font-medium">{klass?.label ?? t('workOrder.classMissing', { id: link.edition_id })}</p>
      {klass ? (
        <dl className="grid gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
          <Field label={t('workOrder.classStatus')} value={klass.status} />
          <Field label={t('workOrder.courseStatus')} value={klass.course_status} />
          <Field label={t('workOrder.startDate')} value={klass.start_date ? formatDate(klass.start_date) : null} />
          <Field label={t('workOrder.endDate')} value={klass.end_date ? formatDate(klass.end_date) : null} />
          <Field label={t('workOrder.examDate')} value={klass.exam_date ? formatDate(klass.exam_date) : null} />
          <Field label={t('workOrder.totalHours')} value={klass.total_hours} />
          <Field label={t('workOrder.site')} value={site !== '' ? site : null} />
          <Field label={t('workOrder.learners')} value={klass.learners_count} />
          <Field label={t('workOrder.syncedAt')} value={link.synced_at ? formatDateTime(link.synced_at) : null} />
        </dl>
      ) : null}
      {link.sync_error ? (
        <p role="alert" className="flex items-start gap-1.5 text-xs text-destructive">
          <AlertTriangle className="mt-0.5 size-3.5 shrink-0" aria-hidden />
          <span>{t('workOrder.syncError', { message: link.sync_error })}</span>
        </p>
      ) : null}
    </div>
  )
}
