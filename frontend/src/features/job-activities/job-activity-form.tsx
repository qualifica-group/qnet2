import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { JobActivityFormBody } from '@/features/job-activities/job-activity-form-body'
import { JOB_ACTIVITIES_DOMAIN, type JobActivityDetail } from '@/features/job-activities/types'

interface JobActivityFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (jobActivity: JobActivityDetail) => void
  onCancel: () => void
}

/**
 * The jobActivity create form (there is no edit form: the detail edits in
 * place). Resolves the create-context `ResourcePermissions`
 * (`GET /meta/job-activities`) before rendering `JobActivityFormBody`.
 */
export function JobActivityForm(props: JobActivityFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(JOB_ACTIVITIES_DOMAIN)

  if (metaQuery.isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => void metaQuery.refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (!metaQuery.data) {
    return <RecordFormSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={metaQuery.data.permissions}>
      <JobActivityFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
