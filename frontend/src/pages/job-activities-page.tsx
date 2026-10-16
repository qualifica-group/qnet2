import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { JobActivitiesTable } from '@/features/job-activities/job-activities-table'

/**
 * Job roles page. Light composition only: gates access with `job-activities.view`
 * and mounts the thin adapter over the generic table (`domain="job-activities"`).
 */
export default function JobActivitiesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="job-activities.view"
      fallback={<p className="text-sm text-muted-foreground">{t('jobActivities.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <JobActivitiesTable />
      </div>
    </Can>
  )
}
