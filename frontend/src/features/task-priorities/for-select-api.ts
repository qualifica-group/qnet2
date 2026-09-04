import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the task-priorities for-select endpoint. */
export const TASK_PRIORITIES_FOR_SELECT_RESOURCE = 'task-priorities'

/**
 * The presentation bag this resource projects alongside `{id, label}`, so a
 * picker can draw the SAME badge as the grid without a second request.
 */
export interface TaskPriorityForSelectMeta {
  /** Palette token of `BADGE_COLOR_TOKENS`, never a hex. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
}

/** A single task priority option as returned by `GET /api/task-priorities/for-select`. */
export interface TaskPriorityForSelectItem extends ForSelectItem {
  meta: TaskPriorityForSelectMeta
}

/**
 * Fetches a page of task priority options from `GET /api/task-priorities/for-select`.
 * Reuses the generic fetcher and narrows its meta-less `ForSelectItem` to the
 * richer shape this endpoint actually returns — same approach as
 * `fetchStatusesForReorder`, so the request logic stays in one place.
 * Ungated by permission (ADR 0011): any authenticated actor can resolve
 * options. Inactive rows are omitted unless explicitly requested via `ids`
 * (edit-mode hydration).
 */
export async function fetchTaskPrioritiesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<TaskPriorityForSelectItem>> {
  const response = await fetchForSelect(TASK_PRIORITIES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as TaskPriorityForSelectItem[] }
}

interface UseTaskPrioritiesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a task priority single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `task-priorities` resource.
 */
export function useTaskPrioritiesForSelect({ search, ids, enabled }: UseTaskPrioritiesForSelectOptions) {
  return useForSelect({
    resource: TASK_PRIORITIES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
