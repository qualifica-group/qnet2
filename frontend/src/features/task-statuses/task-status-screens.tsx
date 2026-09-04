/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskStatus } from '@/features/task-statuses/api'
import { TaskStatusForm } from '@/features/task-statuses/task-status-form'
import { TaskStatusDetailView } from '@/features/task-statuses/task-status-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskStatusDetail } from '@/features/task-statuses/types'

/** Query key for a single task status's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['task-statuses', 'detail', id] as const
}

/**
 * Content-only `task-statuses` screens for the module registry (spec 0042): fetch
 * + the existing presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function TaskStatusDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTaskStatus(id))

  if (isError) {
    return (
      <DetailError
        message={t('taskStatuses.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskStatus) {
    return <DetailLoading />
  }

  return <TaskStatusDetailView taskStatus={taskStatus} />
}

export function TaskStatusFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskStatusDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TaskStatusForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <TaskStatusEditScreen taskStatusId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface TaskStatusEditScreenProps {
  taskStatusId: number
  onSuccess: (taskStatus: TaskStatusDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task status detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function TaskStatusEditScreen({ taskStatusId, onSuccess, onCancel }: TaskStatusEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskStatus,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(taskStatusId), () => fetchTaskStatus(taskStatusId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('taskStatuses.detail.loadError')}</p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !taskStatus) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <TaskStatusForm mode={{ type: 'edit', taskStatus }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-statuses',
  basePath: '/task-statuses',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskStatuses',
  DetailScreen: TaskStatusDetailScreen,
  FormScreen: TaskStatusFormScreen,
}
