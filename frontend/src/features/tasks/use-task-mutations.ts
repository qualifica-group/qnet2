import { useMutation, useQueryClient, type QueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import {
  approveTask,
  blockTask,
  completeTask,
  rejectTask,
  requestTaskUpdate,
  taskDetailQueryKey,
  uncompleteTask,
  unblockTask,
} from '@/features/tasks/api'
import type {
  CompleteTaskPayload,
  RequestTaskUpdatePayload,
  TaskDetailWithPermissions,
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
