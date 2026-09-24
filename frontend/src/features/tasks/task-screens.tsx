/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useModuleOpener } from '@/features/modules/use-module-opener'
import { parseEntityId } from '@/routes/entity-id'
import { fetchTask, TASKS_DOMAIN, taskDetailQueryKey } from '@/features/tasks/api'
import { TaskAccessDenied } from '@/features/tasks/task-access-denied'
import { taskAccessDeniedInfo } from '@/features/tasks/task-access-denied-info'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { RecordFormSkeleton } from '@/components/record-form/record-form-skeleton'
import { TaskForm } from '@/features/tasks/task-form'
import { OPEN_MODE_MODAL, OPEN_MODE_PAGE } from '@/features/modules/types'
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
 * AC-085: opening a child goes through the SAME opener every other surface
 * uses, so it honors the actor's own modal/page preference. "Crea sotto-task"
 * instead always mounts the Sheet above the parent (`forceMode`, spec 0067
 * D-3): the parent detail is never abandoned while adding a child. It passes
 * `parent_task_id` through `ModuleCreateParams` (spec 0045) — the single
 * channel a create form gets its context through.
 */
export function TaskDetailScreen({ id, onEdit }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { openView, sheet } = useModuleOpener(TASKS_DOMAIN)
  const { openCreateWith: openSubtaskCreate, sheet: subtaskSheet } = useModuleOpener(TASKS_DOMAIN, {
    forceMode: OPEN_MODE_MODAL,
    viewAfterCreate: true,
    // The parent stays mounted underneath: refresh its `subtasks` list.
    onSaved: () => queryClient.invalidateQueries({ queryKey: taskDetailQueryKey(id) }),
  })
  const { data: task, isLoading, isError, error, refetch } = useEntityDetail(taskDetailQueryKey(id), () =>
    fetchTask(id),
  )
  // Spec 0155 D-7: a 403 here is never transient (no log/Teams alert either,
  // server-side) — the access-denied message and contacts replace the
  // generic error state, with no Riprova.
  const accessDenied = isError ? taskAccessDeniedInfo(error) : null

  // The sheets render OUTSIDE the loading branch: the subtask form reads the
  // parent through this same query key and refetches it on mount. Swapping
  // them for the skeleton during that refetch would remount the form, which
  // refetches again — an endless reload loop.
  let content: ReactNode
  if (accessDenied) {
    content = <TaskAccessDenied message={accessDenied.message} contacts={accessDenied.contacts} />
  } else if (isError) {
    content = (
      <DetailError
        message={t('tasks.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  } else if (isLoading || !task) {
    content = <DetailLoading />
  } else {
    content = (
      <TaskDetailView
        task={task}
        onEdit={onEdit}
        onOpenSubtask={(subtaskId) => openView(asRow(subtaskId))}
        onCreateSubtask={() => openSubtaskCreate({ parent_task_id: task.id })}
      />
    )
  }

  return (
    <>
      {content}
      {sheet}
      {subtaskSheet}
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
    // Spec 0133 D-4: "Nuovo task" from the Commessa detail's Task tab.
    const workOrderId = parseEntityId(String(mode.params?.work_order_id ?? ''))
    // Spec 0146 AC-030: "+ Task" of a fase on the Task board.
    const workOrderStageId = parseEntityId(String(mode.params?.work_order_stage_id ?? ''))
    return (
      <TaskForm
        mode={{ type: 'create', parentTaskId, workOrderId, workOrderStageId }}
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
    return <RecordFormSkeleton />
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
  // The "Modifica" action lives on the task card itself, as on Opportunita'.
  detailOwnsEditAction: true,
  // The form renders its own sticky identity bar (heading + actions), so the
  // hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
