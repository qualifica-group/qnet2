import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { TimeEntriesDashboard } from '@/features/time-entries/time-entries-dashboard'

/**
 * Segnatempo dashboard page (spec 0122 D-1): gates `time-entries.viewAny`
 * and mounts the day-grouped dashboard. Unlike every other module this is
 * NOT an AG Grid + `RecordCanvas` list, so no generic table is involved here.
 */
export default function TimeEntriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="time-entries.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('timeEntries.page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader title={t('timeEntries.page.title')} subtitle={t('timeEntries.page.subtitle')} />
        <TimeEntriesDashboard />
      </div>
    </Can>
  )
}
