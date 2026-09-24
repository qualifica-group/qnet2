import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import {
  approveTask,
  blockTask,
  completeTask,
  rejectTask,
  reorderTaskSubtasks,
  requestTaskUpdate,
  taskDetailQueryKey,
  uncompleteTask,
  unblockTask,
} from '@/features/tasks/api'
import type {
  CompleteTaskPayload,
  RequestTaskUpdatePayload,
  TaskDetailWithPermissions,
  TaskSubtask,
} from '@/features/tasks/types'
import { timeEntryKeys } from '@/features/time-entries/query-keys'

interface TaskMutationOptions {
  taskId: number
  /**
   * Receives the task WITH its refreshed `permissions.actions` (spec 0116
   * D-8): the six flags follow the task's own phase/`is_blocked`, so a
   * caller merging only `data` would keep rendering the previous state's
   * buttons until a reload. Mirrors `ContractMutationOptions.onSuccess`.
   */
  onSuccess?: (task: TaskDetailWithPermissions) => void
}

/** Seeds the fresh detail into the query cache and hands it to the caller (shared by every action below). */
function seedAndNotify(
  queryClient: QueryClient,
  taskId: number,
  task: TaskDetailWithPermissions,
  onSuccess?: (task: TaskDetailWithPermissions) => void,
): void {
  queryClient.setQueryData(taskDetailQueryKey(taskId), task)
  onSuccess?.(task)
}

/**
 * Every body-less domain action (uncomplete/approve/reject/block/unblock)
 * shares this shape: call the endpoint, seed the fresh detail into the query
 * cache, let the caller close its own dialog/toast. Mirrors
 * `useContractMutation`.
 *
 * The error is NOT caught here (engineering.md §1.4, dead-end swallow): a
 * caller distinguishes 409 (Task bloccato, D-8) from 422 (wrong phase,
 * `TaskActionAvailability`) by reading `error.response?.status` off the
 * rejected promise (`axios.isAxiosError`), exactly as
 * `useTaskRowActions.runDelete` already does for delete's own 403/409 split.
 */
function useTaskActionMutation(
  { taskId, onSuccess }: TaskMutationOptions,
  action: (id: number) => Promise<TaskDetailWithPermissions>,
) {
  const queryClient = useQueryClient()

  return useMutation<TaskDetailWithPermissions, AxiosError<ApiErrorResponse>, void>({
    mutationFn: () => action(taskId),
    onSuccess: (task) => seedAndNotify(queryClient, taskId, task, onSuccess),
  })
}

/**
 * "Completa": CASO 1 (chiusura) sends `closure_feedback`, CASO 2 (richiedi
 * validazione) sends `validation_status_id` instead; both now carry a
 * mandatory `time_entry` (spec 0123 D-1). 422 on the missing feedback / wrong
 * `validation_status_id` phase / `time_entry.*` is a distinct branch from the
 * generic completable-phase 422 — the caller reads `error.response.data.errors`
 * to tell them apart (mirrors `applyServerValidationErrors`' field mapping).
 */
export function useCompleteTask({ taskId, onSuccess }: TaskMutationOptions) {
  const queryClient = useQueryClient()

  return useMutation<TaskDetailWithPermissions, AxiosError<ApiErrorResponse>, CompleteTaskPayload>({
    mutationFn: (payload) => completeTask(taskId, payload),
    onSuccess: async (task) => {
      seedAndNotify(queryClient, taskId, task, onSuccess)
      // The segnatempo `/complete` creates is NOT in the response body (D-1):
      // the Task's own segnatempo query is invalidated explicitly (AC-040).
      await queryClient.invalidateQueries({ queryKey: timeEntryKeys.taskEntries(taskId) })
    },
  })
}

export function useUncompleteTask(options: TaskMutationOptions) {
  return useTaskActionMutation(options, uncompleteTask)
}

export function useApproveTask(options: TaskMutationOptions) {
  return useTaskActionMutation(options, approveTask)
}

export function useRejectTask(options: TaskMutationOptions) {
  return useTaskActionMutation(options, rejectTask)
}

export function useBlockTask(options: TaskMutationOptions) {
  return useTaskActionMutation(options, blockTask)
}

export function useUnblockTask(options: TaskMutationOptions) {
  return useTaskActionMutation(options, unblockTask)
}

/** Reorders the cached `subtasks[]` to match `ids`, dropping any id the cache does not (yet) know about. */
function reorderedSubtasks(current: TaskSubtask[], ids: number[]): TaskSubtask[] {
  const byId = new Map(current.map((subtask) => [subtask.id, subtask]))
  return ids
    .map((id, index) => {
      const subtask = byId.get(id)
      return subtask ? { ...subtask, position: index } : null
    })
    .filter((subtask): subtask is TaskSubtask => subtask !== null)
}

interface ReorderTaskSubtasksContext {
  previous: TaskDetailWithPermissions | undefined
}

/**
 * "Riordino sotto-task" (spec 0155 D-4/AC-006/AC-007): optimistic — the panel
 * reorders instantly, `onError` rolls the parent's cached detail back to its
 * pre-drag snapshot, `onSuccess` reconciles with the server's own
 * `position`/`permissions` (refreshed per row, unlike the optimistic guess).
 */
export function useReorderTaskSubtasks(taskId: number) {
  const queryClient = useQueryClient()

  return useMutation<TaskSubtask[], AxiosError<ApiErrorResponse>, number[], ReorderTaskSubtasksContext>({
    mutationFn: (ids) => reorderTaskSubtasks(taskId, ids),
    onMutate: async (ids) => {
      await queryClient.cancelQueries({ queryKey: taskDetailQueryKey(taskId) })
      const previous = queryClient.getQueryData<TaskDetailWithPermissions>(taskDetailQueryKey(taskId))
      if (previous) {
        queryClient.setQueryData(taskDetailQueryKey(taskId), {
          ...previous,
          subtasks: reorderedSubtasks(previous.subtasks, ids),
        })
      }
      return { previous }
    },
    onError: (_error, _ids, context) => {
      if (context?.previous) {
        queryClient.setQueryData(taskDetailQueryKey(taskId), context.previous)
      }
    },
    onSuccess: (subtasks) => {
      const current = queryClient.getQueryData<TaskDetailWithPermissions>(taskDetailQueryKey(taskId))
      if (current) {
        queryClient.setQueryData(taskDetailQueryKey(taskId), { ...current, subtasks })
      }
    },
  })
}

/**
 * "Richiedi aggiornamento" (spec 0118 D-10..D-14): unlike the other five
 * body-less actions, it carries a payload (`recipient_ids`/`message`), so it
 * follows `useCompleteTask`'s shape rather than `useTaskActionMutation`'s.
 */
export function useRequestTaskUpdate({ taskId, onSuccess }: TaskMutationOptions) {
  const queryClient = useQueryClient()

  return useMutation<TaskDetailWithPermissions, AxiosError<ApiErrorResponse>, RequestTaskUpdatePayload>({
    mutationFn: (payload) => requestTaskUpdate(taskId, payload),
    onSuccess: (task) => seedAndNotify(queryClient, taskId, task, onSuccess),
  })
}
