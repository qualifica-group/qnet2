/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useFormLeaveGuard } from '@/features/modules/use-form-leave-guard'
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
 * AC-085 (user directive 2026-10-06): opening a child and "Crea sotto-task"
 * both mount the Sheet above the parent, whatever the actor's open-mode
 * preference (`forceMode`, spec 0067 D-3): the parent detail is never
 * abandoned while working on a child, and every save in the Sheet refreshes
 * its `subtasks` list. The create passes `parent_task_id` through
 * `ModuleCreateParams` (spec 0045) — the single channel a create form gets
 * its context through.
 *
 * Spec 0195: the detail edits its fields in place, so `onEdit` is never
 * used; each save reports through `onChanged` (the modal host refreshes its
 * grid and keeps the record open).
 */
export function TaskDetailScreen({ id, onChanged }: ModuleDetailScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const {
    openView: openSubtask,
    openCreateWith: openSubtaskCreate,
    sheet: subtaskSheet,
  } = useModuleOpener(TASKS_DOMAIN, {
    forceMode: OPEN_MODE_MODAL,
    viewAfterCreate: true,
    // The parent stays mounted underneath: refresh its `subtasks` list after
    // a create, or after an in-place save on an opened child.
    onSaved: () => queryClient.invalidateQueries({ queryKey: taskDetailQueryKey(id) }),
  })
  const { data: task, isLoading, isError, error, refetch } = useEntityDetail(taskDetailQueryKey(id), () =>
    fetchTask(id),
  )
  // Spec 0155 D-7: a 403 here is never transient (no log/Teams alert either,
  // server-side) — the access-denied message and contacts replace the
  // generic error state, with no Riprova.
  const accessDenied = isError ? taskAccessDeniedInfo(error) : null

  // The sheet renders OUTSIDE the loading branch: the subtask form reads the
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
        onChanged={onChanged}
        onOpenSubtask={(subtaskId) => openSubtask(asRow(subtaskId))}
        onCreateSubtask={() => openSubtaskCreate({ parent_task_id: task.id })}
      />
    )
  }

  return (
    <>
      {content}
      {subtaskSheet}
    </>
  )
}

export function TaskFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  // Spec 0195 (user directive 2026-10-06): leaving a task being created —
  // Cancel, the Sheet's X/overlay/Esc, a link, a reload — always asks first.
  const leaveGuard = useFormLeaveGuard({
    title: t('tasks.form.leaveConfirm.title'),
    description: t('tasks.form.leaveConfirm.description'),
    confirmLabel: t('tasks.form.leaveConfirm.confirm'),
    cancelLabel: t('tasks.form.leaveConfirm.cancel'),
    tone: 'warning',
  })

  const handleSuccess = (saved: TaskDetail) => {
    queryClient.invalidateQueries({ queryKey: taskDetailQueryKey(saved.id) })
    // Saved: the navigation to the new task's detail is no "leaving".
    leaveGuard.allowLeave()
    onSuccess(saved.id)
  }

  const handleCancel = async () => {
    if (await leaveGuard.confirmLeave()) {
      onCancel()
    }
  }

  if (mode.type === 'create') {
    // `parent_task_id` arrives as a `number` from the modal caller and as a
    // `string` from the page deep-link's query string: normalize both.
    const parentTaskId = parseEntityId(String(mode.params?.parent_task_id ?? ''))
    // Spec 0133 D-4: "Nuovo task" from the Commessa detail's Task tab.
    const workOrderId = parseEntityId(String(mode.params?.work_order_id ?? ''))
    // Spec 0146 AC-030: "+ Task" of a fase on the Task board.
    const workOrderStageId = parseEntityId(String(mode.params?.work_order_stage_id ?? ''))
    // Spec 0157 D-4: the Kanban's per-column "+" (status OR due-date board).
    const taskStatusId = parseEntityId(String(mode.params?.task_status_id ?? ''))
    const endDate = mode.params?.end_date != null ? String(mode.params.end_date) : null
    // Spec 0199: "Nuovo task" from the anagrafica detail's Task tab.
    const registryId = parseEntityId(String(mode.params?.registry_id ?? ''))
    return (
      <>
        {leaveGuard.navigationGuard}
        <TaskForm
          mode={{ type: 'create', parentTaskId, workOrderId, workOrderStageId, taskStatusId, endDate, registryId }}
          onSuccess={handleSuccess}
          onCancel={() => void handleCancel()}
        />
      </>
    )
  }

  if (mode.type === 'duplicate') {
    return (
      <>
        {leaveGuard.navigationGuard}
        <TaskDuplicateScreen taskId={mode.id} onSuccess={handleSuccess} onCancel={() => void handleCancel()} />
      </>
    )
  }

  // Spec 0195: no edit form — the detail edits in place, and the registry
  // generates no `:id/edit` route (`generateEditRoute: false`).
  return null
}

interface TaskDuplicateScreenProps {
  taskId: number
  onSuccess: (task: TaskDetail) => void
  onCancel: () => void
}

/**
 * Row action "duplicate" (spec 0156 D-4): fetches the fresh, re-authorized
 * source task before mounting the create form pre-filled from it — the copy
 * still submits via the create path (`TaskFormMode: 'duplicate'`).
 */
function TaskDuplicateScreen({ taskId, onSuccess, onCancel }: TaskDuplicateScreenProps) {
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

  return <TaskForm mode={{ type: 'duplicate', source: task }} onSuccess={onSuccess} onCancel={onCancel} />
}

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: TASKS_DOMAIN,
  basePath: '/tasks',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.tasks',
  DetailScreen: TaskDetailScreen,
  FormScreen: TaskFormScreen,
  // Spec 0195: the detail IS the edit form — no edit route, no Edit button.
  generateEditRoute: false,
  detailOwnsEditAction: true,
  // The form renders its own sticky identity bar (heading + actions), so the
  // hosts must not stack a second heading above it.
  formOwnsHeader: true,
}
