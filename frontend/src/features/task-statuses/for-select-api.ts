import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'
import type { TaskStatusSystemKey } from '@/features/task-statuses/types'

/** Resource segment for the task-statuses for-select endpoint. */
export const TASK_STATUSES_FOR_SELECT_RESOURCE = 'task-statuses'

/**
 * The presentation bag this resource projects alongside `{id, label}`, so a
 * picker can draw the SAME badge as the grid without a second request.
 * `completion_percentage` is what makes the Task form's derived percentage
 * (spec 0101 AC-084) update on status change with no extra call, `system_key`
 * is what identifies the three system rows (D-5) and `group` the phase every
 * row belongs to, system or custom.
 */
export interface TaskStatusForSelectMeta {
  /** Palette token of `BADGE_COLOR_TOKENS`, never a hex. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
  system_key: TaskStatusSystemKey
  group: TaskStatusGroupValue
  completion_percentage: number
}

/** A single task status option as returned by `GET /api/task-statuses/for-select`. */
export interface TaskStatusForSelectItem extends ForSelectItem {
  meta: TaskStatusForSelectMeta
}

/**
 * Fetches a page of task status options from `GET /api/task-statuses/for-select`.
 * Reuses the generic fetcher and narrows its meta-less `ForSelectItem` to the
 * richer shape this endpoint actually returns — same approach as
 * `fetchStatusesForReorder`, so the request logic stays in one place.
 * Ungated by permission (ADR 0011): any authenticated actor can resolve
 * options. Inactive rows are omitted unless explicitly requested via `ids`
 * (edit-mode hydration).
 */
export async function fetchTaskStatusesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<TaskStatusForSelectItem>> {
  const response = await fetchForSelect(TASK_STATUSES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as TaskStatusForSelectItem[] }
}

interface UseTaskStatusesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a task status single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `task-statuses` resource.
 */
export function useTaskStatusesForSelect({ search, ids, enabled }: UseTaskStatusesForSelectOptions) {
  return useForSelect({
    resource: TASK_STATUSES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
