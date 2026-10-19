import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { JobRoleFormBody } from '@/features/job-roles/job-role-form-body'
import { JOB_ROLES_DOMAIN, type JobRoleDetail } from '@/features/job-roles/types'

interface JobRoleFormProps {
  /** Called after a successful create so the caller can navigate to the detail. */
  onSuccess: (jobRole: JobRoleDetail) => void
  onCancel: () => void
}

/**
 * The job role create form (there is no edit form: the detail edits in
 * place). Resolves the create-context `ResourcePermissions`
 * (`GET /meta/job-roles`) before rendering `JobRoleFormBody`.
 */
export function JobRoleForm(props: JobRoleFormProps) {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(JOB_ROLES_DOMAIN)

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
      <JobRoleFormBody {...props} />
    </ResourcePermissionsProvider>
  )
}
