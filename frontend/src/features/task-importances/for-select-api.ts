import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the task-importances for-select endpoint. */
export const TASK_IMPORTANCES_FOR_SELECT_RESOURCE = 'task-importances'

/**
 * The presentation bag this resource projects alongside `{id, label}`, so a
 * picker can draw the SAME badge as the grid without a second request.
 */
export interface TaskImportanceForSelectMeta {
  /** Palette token of `BADGE_COLOR_TOKENS`, never a hex. */
  color: string
  /** Curated lucide name of `ICON_NAMES`, or null when unset. */
  icon: string | null
}

/** A single task importance option as returned by `GET /api/task-importances/for-select`. */
export interface TaskImportanceForSelectItem extends ForSelectItem {
  meta: TaskImportanceForSelectMeta
}

/**
 * Fetches a page of task importance options from `GET /api/task-importances/for-select`.
 * Reuses the generic fetcher and narrows its meta-less `ForSelectItem` to the
 * richer shape this endpoint actually returns — same approach as
 * `fetchStatusesForReorder`, so the request logic stays in one place.
 * Ungated by permission (ADR 0011): any authenticated actor can resolve
 * options. Inactive rows are omitted unless explicitly requested via `ids`
 * (edit-mode hydration).
 */
export async function fetchTaskImportancesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<TaskImportanceForSelectItem>> {
  const response = await fetchForSelect(TASK_IMPORTANCES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as TaskImportanceForSelectItem[] }
}

interface UseTaskImportancesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a task importance single-select: debounced server search,
 * offset pagination and `ids[]` hydration, bound to the `task-importances` resource.
 */
export function useTaskImportancesForSelect({ search, ids, enabled }: UseTaskImportancesForSelectOptions) {
  return useForSelect({
    resource: TASK_IMPORTANCES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
