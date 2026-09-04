/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { parseEntityId } from '@/routes/entity-id'
import { fetchTask, TASKS_DOMAIN, taskDetailQueryKey } from '@/features/tasks/api'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { TaskForm, TaskFormSkeleton } from '@/features/tasks/task-form'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { TableRow } from '@/features/table/types'
import type { TaskDetail } from '@/features/tasks/types'

/**
 * `useModuleOpener` speaks the grid's `TableRow` but only ever reads `id` off
 * it: the detail screen is mounted by id and fetches its own data. The empty
 * `actions` satisfies the shape without pretending this row came from a grid.
 */
function asRow(id: number): TableRow {
  return { id, actions: [] }
}

/**
 * Content-only `tasks` screens for the module registry (spec 0042): fetch +
 * the presentational view/form, no page chrome. Reused as-is by the modal
 * Sheet (`useModuleOpener`) and by the generic dedicated pages
 * (`ModuleDetailPage`/`ModuleFormPage`).
 *
 * AC-085: the two sub-task affordances are instraded through the SAME opener
 * every other surface uses, so opening a child or creating one honors the
 * actor's own modal/page preference. "Crea sotto-task" passes
 * `parent_task_id` through `ModuleCreateParams` (spec 0045) — the single
 * channel a create form gets its context through, in both mount modes.
 */
export function TaskDetailScreen({ id }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const { openView, openCreateWith, sheet } = useModuleOpener(TASKS_DOMAIN)
  const { data: task, isLoading, isError, refetch } = useEntityDetail(taskDetailQueryKey(id), () =>
    fetchTask(id),
  )

  if (isError) {
    return (
      <DetailError
        message={t('tasks.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !task) {
    return <DetailLoading />
  }

  return (
    <>
      <TaskDetailView
        task={task}
        onOpenSubtask={(subtaskId) => openView(asRow(subtaskId))}
        onCreateSubtask={() => openCreateWith({ parent_task_id: task.id })}
      />
      {sheet}
    </>
  )
}

export function TaskFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()

  const handleSuccess = (saved: TaskDetail) => {
    queryClient.invalidateQueries({ queryKey: taskDetailQueryKey(saved.id) })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    // `parent_task_id` arrives as a `number` from the modal caller and as a
    // `string` from the page deep-link's query string: normalize both.
    const parentTaskId = parseEntityId(String(mode.params?.parent_task_id ?? ''))
    return (
      <TaskForm
        mode={{ type: 'create', parentTaskId }}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return <TaskEditScreen taskId={mode.id} onSuccess={handleSuccess} onCancel={onCancel} />
}

interface TaskEditScreenProps {
  taskId: number
  onSuccess: (task: TaskDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized task detail before mounting the edit form,
 * so the partial PATCH starts from authoritative values rather than a stale
 * snapshot.
 */
function TaskEditScreen({ taskId, onSuccess, onCancel }: TaskEditScreenProps) {
  const { t } = useTranslation()
  const { data: task, isLoading, isError, refetch } = useEntityDetail(taskDetailQueryKey(taskId), () =>
    fetchTask(taskId),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('tasks.detail.loadError')}
        </p>
        <Button variant="outline" size="sm" className="bg-card" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !task) {
    return <TaskFormSkeleton />
  }

  return <TaskForm mode={{ type: 'edit', task }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: TASKS_DOMAIN,
  basePath: '/tasks',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.tasks',
  DetailScreen: TaskDetailScreen,
  FormScreen: TaskFormScreen,
}
