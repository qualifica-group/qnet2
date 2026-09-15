import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { TimeEntriesDashboard } from '@/features/time-entries/time-entries-dashboard'

/**
 * Segnatempo dashboard page (spec 0122 D-1): gates `time-entries.viewAny`
 * and mounts the day-grouped dashboard. Unlike every other module this is
 * NOT an AG Grid + `RecordCanvas` list, so no generic table is involved here;
 * the dashboard still owns the standard `PageHeader` (breadcrumb + create
 * action), like the table adapters do.
 */
export default function TimeEntriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="time-entries.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('timeEntries.page.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <TimeEntriesDashboard />
      </div>
    </Can>
  )
}
