/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskType } from '@/features/task-types/api'
import { TaskTypeForm } from '@/features/task-types/task-type-form'
import { TaskTypeDetailView } from '@/features/task-types/task-type-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskTypeDetail } from '@/features/task-types/types'

/** Query key for a single task type's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['task-types', 'detail', id] as const
}

/**
 * Content-only `task-types` screens for the module registry (spec 0042): fetch
 * + the existing presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function TaskTypeDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTaskType(id))

  if (isError) {
    return (
      <DetailError
        message={t('taskTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskType) {
    return <DetailLoading />
  }

  return <TaskTypeDetailView taskType={taskType} />
}

export function TaskTypeFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskTypeDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TaskTypeForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <TaskTypeEditScreen taskTypeId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface TaskTypeEditScreenProps {
  taskTypeId: number
  onSuccess: (taskType: TaskTypeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task type detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function TaskTypeEditScreen({ taskTypeId, onSuccess, onCancel }: TaskTypeEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(taskTypeId), () => fetchTaskType(taskTypeId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('taskTypes.detail.loadError')}</p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !taskType) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <TaskTypeForm mode={{ type: 'edit', taskType }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-types',
  basePath: '/task-types',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskTypes',
  DetailScreen: TaskTypeDetailScreen,
  FormScreen: TaskTypeFormScreen,
}
