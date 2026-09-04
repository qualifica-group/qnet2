/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchTaskCategory } from '@/features/task-categories/api'
import { TaskCategoryForm } from '@/features/task-categories/task-category-form'
import { TaskCategoryDetailView } from '@/features/task-categories/task-category-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TaskCategoryDetail } from '@/features/task-categories/types'

/** Query key for a single task category's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['task-categories', 'detail', id] as const
}

/**
 * Content-only `task-categories` screens for the module registry (spec 0042): fetch
 * + the existing presentational view/form, no page chrome. Reused as-is by the
 * modal Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 */
export function TaskCategoryDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskCategory,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(id), () => fetchTaskCategory(id))

  if (isError) {
    return (
      <DetailError
        message={t('taskCategories.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !taskCategory) {
    return <DetailLoading />
  }

  return <TaskCategoryDetailView taskCategory={taskCategory} />
}

export function TaskCategoryFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskCategoryDetail) => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return <TaskCategoryForm mode={{ type: 'create' }} onSuccess={handleSuccess} onCancel={onCancel} />
  }

  return (
    <TaskCategoryEditScreen taskCategoryId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
  )
}

interface TaskCategoryEditScreenProps {
  taskCategoryId: number
  onSuccess: (taskCategory: TaskCategoryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task category detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot.
 */
function TaskCategoryEditScreen({ taskCategoryId, onSuccess, onCancel }: TaskCategoryEditScreenProps) {
  const { t } = useTranslation()
  const {
    data: taskCategory,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(taskCategoryId), () => fetchTaskCategory(taskCategoryId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('taskCategories.detail.loadError')}</p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !taskCategory) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <TaskCategoryForm mode={{ type: 'edit', taskCategory }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'task-categories',
  basePath: '/task-categories',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.taskCategories',
  DetailScreen: TaskCategoryDetailScreen,
  FormScreen: TaskCategoryFormScreen,
}
