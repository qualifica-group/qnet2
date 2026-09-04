/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskPriority } from '@/features/task-priorities/api'
import { TaskPriorityForm } from '@/features/task-priorities/task-priority-form'
import { TaskPriorityDetailView } from '@/features/task-priorities/task-priority-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskPriorityDetail } from '@/features/task-priorities/types'

/** Query key for a single task priority's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['task-priorities', 'detail', id] as const
}

/**
 * Content-only `task-priorities` screens for the module registry (spec 0042): fetch
 * + the existing presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function TaskPriorityDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskPriority,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTaskPriority(id))

  if (isError) {
    return (
      <DetailError
        message={t('taskPriorities.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskPriority) {
    return <DetailLoading />
  }

  return <TaskPriorityDetailView taskPriority={taskPriority} />
}

export function TaskPriorityFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskPriorityDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TaskPriorityForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <TaskPriorityEditScreen taskPriorityId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface TaskPriorityEditScreenProps {
  taskPriorityId: number
  onSuccess: (taskPriority: TaskPriorityDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task priority detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function TaskPriorityEditScreen({ taskPriorityId, onSuccess, onCancel }: TaskPriorityEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskPriority,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(taskPriorityId), () => fetchTaskPriority(taskPriorityId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('taskPriorities.detail.loadError')}</p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !taskPriority) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <TaskPriorityForm mode={{ type: 'edit', taskPriority }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-priorities',
  basePath: '/task-priorities',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskPriorities',
  DetailScreen: TaskPriorityDetailScreen,
  FormScreen: TaskPriorityFormScreen,
}
