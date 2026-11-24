import { useTranslation } from 'react-i18next'
import { BookOpen, CircleCheck, CircleX, MapPin, Users } from 'lucide-react'
import type { ReactNode } from 'react'
import { Badge } from '@/components/ui/badge'
import { formatDate } from '@/lib/formatting/date-display'
import type { StatementClass } from '@/integrations/formalab/learner-statement/types'

/** Course, site and learners outcome strip of the prospetto header (legacy "INFO CORSO"). */
export function CourseInfo({ info }: { info: StatementClass }) {
  const { t } = useTranslation('formalab')
  const dates = info.start_date || info.end_date ? `${formatDate(info.start_date)} → ${formatDate(info.end_date)}` : null

  return (
    <div className="grid gap-3 rounded-lg border bg-card p-3 text-xs sm:grid-cols-3">
      <InfoBlock icon={<BookOpen className="size-3.5 text-primary" aria-hidden="true" />} label={t('learnerStatement.course.course')}>
        <strong className="block">{info.title || '-'}</strong>
        {info.status ? (
          <span className="text-muted-foreground">
            {t('learnerStatement.course.status')} <Badge variant="secondary" className="text-[10px]">{info.status}</Badge>
            {dates ? ` · ${dates}` : null}
          </span>
        ) : null}
      </InfoBlock>
      <InfoBlock icon={<MapPin className="size-3.5 text-destructive" aria-hidden="true" />} label={t('learnerStatement.course.site')}>
        <strong className="block">{info.site_name ?? '-'}</strong>
        <span className="text-muted-foreground">
          {[info.site_address, info.site_city].filter(Boolean).join(', ')}
          {info.site_code ? (
            <Badge variant="outline" className="ml-1 text-[10px]">
              {t('learnerStatement.course.siteCode', { code: info.site_code })}
            </Badge>
          ) : null}
        </span>
      </InfoBlock>
      <InfoBlock icon={<Users className="size-3.5 text-success" aria-hidden="true" />} label={t('learnerStatement.course.learners')}>
        <strong className="block">{t('learnerStatement.course.total', { count: info.learners_total })}</strong>
        <span className="inline-flex flex-wrap items-center gap-1">
          <span className="inline-flex items-center gap-0.5">
            <CircleCheck className="size-3 text-success" aria-hidden="true" />
            {t('learnerStatement.course.passed', { count: info.learners_passed })}
          </span>
          {info.learners_failed > 0 ? (
            <span className="inline-flex items-center gap-0.5">
              · <CircleX className="size-3 text-destructive" aria-hidden="true" />
              {t('learnerStatement.course.failed', { count: info.learners_failed })}
            </span>
          ) : null}
          {info.learners_total > 0 ? (
            <Badge variant="secondary" className="bg-success/20 text-[10px] text-foreground">
              {`${info.passed_percentage}%`}
            </Badge>
          ) : null}
        </span>
      </InfoBlock>
    </div>
  )
}

function InfoBlock({ icon, label, children }: { icon: ReactNode; label: string; children: ReactNode }) {
  return (
    <div className="flex min-w-0 items-start gap-1.5">
      <span className="mt-0.5">{icon}</span>
      <div className="min-w-0">
        <span className="block text-[10px] uppercase tracking-wide text-muted-foreground">{label}</span>
        {children}
      </div>
    </div>
  )
}
