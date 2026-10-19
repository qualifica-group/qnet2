/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { jobRoleKeys, fetchJobRole } from '@/features/job-roles/api'
import { JobRoleForm } from '@/features/job-roles/job-role-form'
import { JobRoleDetailView } from '@/features/job-roles/job-role-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { JOB_ROLES_DOMAIN, type JobRoleDetail } from '@/features/job-roles/types'

/**
 * Content-only `job-roles` screens for the module registry (spec 0042). The
 * detail edits its rows in place, so `onEdit` is never used; each save
 * reports through `onChanged`.
 */
export function JobRoleDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: jobRole,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(jobRoleKeys.detail(id), () => fetchJobRole(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('jobRoles.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !jobRole) {
    return <DetailLoading />
  }

  return <JobRoleDetailView jobRole={jobRole} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function JobRoleFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: JobRoleDetail) => {
    void queryClient.invalidateQueries({ queryKey: jobRoleKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <JobRoleForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: JOB_ROLES_DOMAIN,
  basePath: `/${JOB_ROLES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.jobRoles',
  DetailScreen: JobRoleDetailScreen,
  FormScreen: JobRoleFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
