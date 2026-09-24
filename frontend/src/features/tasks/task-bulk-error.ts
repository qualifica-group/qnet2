/**
 * Error handling for `POST /api/tasks/bulk` (spec 0156 D-6): all-or-nothing,
 * so a 422 means NOTHING changed and the response carries WHICH tasks were
 * refused and why (`incompatible_tasks`, already the server's own i18n
 * message per row) — distinct from the board's own `bulkTaskAction`, whose
 * 200 reports a per-row succeeded/failed split instead.
 */
import axios from 'axios'
import type { TFunction } from 'i18next'
import type { ApiErrorResponse } from '@/api/types'
import type { TaskBulkIncompatibleTask } from '@/features/tasks/task-bulk-types'

/** The 422 envelope's own extra field, on top of the standard `errors` map. */
interface TaskBulkErrorResponse extends ApiErrorResponse {
  incompatible_tasks?: TaskBulkIncompatibleTask[]
}

/** The `incompatible_tasks` list off a rejected bulk request, or `null` when the failure carries none (a 403, a network error). */
export function taskBulkIncompatibleTasks(error: unknown): TaskBulkIncompatibleTask[] | null {
  if (!axios.isAxiosError<TaskBulkErrorResponse>(error) || error.response?.status !== 422) {
    return null
  }
  const list = error.response.data?.incompatible_tasks
  return list && list.length > 0 ? list : null
}

/**
 * One toast for a failed bulk action: lists every incompatible task's own
 * reason when present, falls back to the shared 403/generic split otherwise.
 */
export function taskBulkErrorDescription(t: TFunction, error: unknown): { message: string; reasons: string[] } {
  const incompatible = taskBulkIncompatibleTasks(error)
  if (incompatible) {
    return {
      message: t('tasks.bulk.incompatibleError', { count: incompatible.length }),
      reasons: incompatible.map((task) => t('tasks.bulk.incompatibleReason', { id: task.id, reason: task.reason })),
    }
  }
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  return {
    message: status === 403 ? t('tasks.bulk.forbidden') : t('tasks.bulk.genericError'),
    reasons: [],
  }
}
