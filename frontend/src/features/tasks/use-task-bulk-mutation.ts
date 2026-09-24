import { useMutation } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { bulkTaskAction } from '@/features/tasks/api'
import type { TaskBulkPayload, TaskBulkResult } from '@/features/tasks/task-bulk-types'

/**
 * Thin, action-agnostic wrapper over `POST /api/tasks/bulk` (spec 0156 D-6):
 * every dialog/simple-confirm handler shares this one mutation, distinguished
 * only by the `payload.action` it submits. The caller (`use-task-bulk-actions-slot.ts`,
 * or a dialog directly) owns the success toast/grid-refresh and the
 * error-toast split (`task-bulk-error.ts`) — this hook stays a plain
 * fetch-and-return, mirroring `useTaskActionMutation`'s own shape.
 */
export function useTaskBulkMutation() {
  return useMutation<TaskBulkResult, AxiosError<ApiErrorResponse>, TaskBulkPayload>({
    mutationFn: (payload) => bulkTaskAction(payload),
  })
}
