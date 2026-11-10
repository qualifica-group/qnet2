/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { jobRoleKeys, fetchJobRole } from '@/features/job-roles/api'
import { JobRoleForm } from '@/features/job-roles/job-role-form'
import { JobRoleDetailView } from '@/features/job-roles/job-role-detail'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import { type JobRoleDetail } from '@/features/job-roles/types'

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

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: JobRoleDetailScreen,
  FormScreen: JobRoleFormScreen,
}
