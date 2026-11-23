/**
 * Spec 0418: completing a task with open sub-tasks closes them with it. This
 * hook reads those sub-tasks (`GET /tasks/{task}/open-subtasks`, only while
 * the Completa pop-up is open) and derives what the pop-up needs from them:
 * the "Ripartizione" rows (the task first, then its sub-tasks in tree order),
 * and whether the segnatempo or the feedback is required by ANY of the tasks
 * the write closes. Without open sub-tasks it is inert.
 */
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { TFunction } from 'i18next'
import { toast } from 'sonner'
import { fetchTaskOpenSubtasks, taskOpenSubtasksQueryKey } from '@/features/tasks/api'
import { taskBulkErrorDescription, taskBulkIncompatibleTasks } from '@/features/tasks/task-bulk-error'
import type { TaskShareTarget, useTaskBulkTimeEntryShares } from '@/features/tasks/use-task-bulk-time-entry-shares'
import type { TaskDetailWithPermissions, TaskOpenSubtask } from '@/features/tasks/types'

const NO_SUBTASKS: TaskOpenSubtask[] = []
const NO_TARGETS: TaskShareTarget[] = []
/** Visual indentation of a sub-task row in the "Ripartizione" list, one step per level. */
const DEPTH_MARKER = '› '

export function useTaskCompleteCascade(task: TaskDetailWithPermissions, open: boolean) {
  const active = task.open_subtasks_count > 0
  const query = useQuery<TaskOpenSubtask[], AxiosError>({
    queryKey: taskOpenSubtasksQueryKey(task.id),
    queryFn: () => fetchTaskOpenSubtasks(task.id),
    enabled: active && open,
    staleTime: 0,
  })
  const subtasks = active ? (query.data ?? NO_SUBTASKS) : NO_SUBTASKS
  const targets = active
    ? [
        { id: task.id, title: task.title },
        ...subtasks.map((subtask) => ({ id: subtask.id, title: `${DEPTH_MARKER.repeat(subtask.depth)}${subtask.title}` })),
      ]
    : NO_TARGETS

  return {
    active,
    count: task.open_subtasks_count,
    isLoading: active && query.isPending,
    isError: active && query.isError,
    targets,
    requiresTimeEntry: task.requires_time_entry || subtasks.some((subtask) => subtask.requires_time_entry),
    requiresClosureFeedback:
      task.requires_closure_feedback || subtasks.some((subtask) => subtask.requires_closure_feedback),
  }
}

/**
 * The cascade's own 422s: a share error goes under the "Ripartizione"
 * section, a refused sub-task (`incompatible_tasks`, D-4) becomes one toast
 * listing every reason. `true` when the error was one of those.
 */
export function handleTaskCompleteCascadeError(
  t: TFunction,
  error: unknown,
  shares: ReturnType<typeof useTaskBulkTimeEntryShares>,
): boolean {
  if (shares.applyServerError(error)) {
    return true
  }
  if (taskBulkIncompatibleTasks(error) === null) {
    return false
  }
  const { message, reasons } = taskBulkErrorDescription(t, error)
  toast.error(message, { description: reasons.join(' ') })
  return true
}
