/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreens map colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { jobActivityKeys, fetchJobActivity } from '@/features/job-activities/api'
import { JobActivityForm } from '@/features/job-activities/job-activity-form'
import { JobActivityDetailView } from '@/features/job-activities/job-activity-detail'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleScreens,
} from '@/features/modules/types'
import { type JobActivityDetail } from '@/features/job-activities/types'

/**
 * Content-only `job-activities` screens for the module registry (spec 0042). The
 * detail edits its rows in place, so `onEdit` is never used; each save
 * reports through `onChanged`.
 */
export function JobActivityDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: jobActivity,
    isLoading,
    isError,
    error,
    refetch,
  } = useEntityDetail(jobActivityKeys.detail(id), () => fetchJobActivity(id))

  if (isError) {
    return (
      <DetailError
        error={error}
        message={t('jobActivities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !jobActivity) {
    return <DetailLoading />
  }

  return <JobActivityDetailView jobActivity={jobActivity} onChanged={onChanged} />
}

/** Create only: there is no edit form, and the registry generates no `:id/edit` route. */
export function JobActivityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  if (mode.type !== 'create') {
    return null
  }

  const handleSuccess = (saved: JobActivityDetail) => {
    void queryClient.invalidateQueries({ queryKey: jobActivityKeys.detail(saved.id) })
    onSuccess(saved.id)
  }

  return <JobActivityForm onSuccess={handleSuccess} onCancel={onCancel} />
}

/** Screens of this module, loaded on first use by the module registry (spec 0042). */
export const moduleScreens: ModuleScreens = {
  DetailScreen: JobActivityDetailScreen,
  FormScreen: JobActivityFormScreen,
}
