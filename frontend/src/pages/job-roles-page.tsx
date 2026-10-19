import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { JobRolesTable } from '@/features/job-roles/job-roles-table'

/**
 * Job roles page. Light composition only: gates access with `job-roles.view`
 * and mounts the thin adapter over the generic table (`domain="job-roles"`).
 */
export default function JobRolesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="job-roles.view"
      fallback={<p className="text-sm text-muted-foreground">{t('jobRoles.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <JobRolesTable />
      </div>
    </Can>
  )
}
