/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { jobActivityKeys, fetchJobActivity } from '@/features/job-activities/api'
import { JobActivityForm } from '@/features/job-activities/job-activity-form'
import { JobActivityDetailView } from '@/features/job-activities/job-activity-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import { JOB_ACTIVITIES_DOMAIN, type JobActivityDetail } from '@/features/job-activities/types'

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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: JOB_ACTIVITIES_DOMAIN,
  basePath: `/${JOB_ACTIVITIES_DOMAIN}`,
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.jobActivities',
  DetailScreen: JobActivityDetailScreen,
  FormScreen: JobActivityFormScreen,
  // The detail IS the edit form: no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The create form renders its own identity band, so hosts must not stack a second heading.
  formOwnsHeader: true,
}
