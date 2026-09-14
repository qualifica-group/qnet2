import { fetchForSelect } from '@/features/for-select/api'
import { useForSelect } from '@/features/for-select/use-for-select'
import type {
  ForSelectItem,
  ForSelectParams,
  PaginatedResponse,
} from '@/features/for-select/types'

/** Resource segment for the task-templates for-select endpoint. */
export const TASK_TEMPLATES_FOR_SELECT_RESOURCE = 'task-templates'

/** The presentation bag this resource projects alongside `{id, label}` (`label` = name, `subtitle` = description). */
export interface TaskTemplateForSelectMeta {
  items_count: number
}

/** A single task template option as returned by `GET /api/task-templates/for-select`. */
export interface TaskTemplateForSelectItem extends ForSelectItem {
  meta: TaskTemplateForSelectMeta
}

/**
 * Fetches a page of task template options from `GET /api/task-templates/for-select`.
 * Reuses the generic fetcher and narrows its meta-less `ForSelectItem` to the
 * richer shape this endpoint actually returns. Ungated by permission (ADR
 * 0011): any authenticated actor can resolve options. Only active templates
 * are returned unless explicitly requested via `ids` (edit-mode hydration).
 */
export async function fetchTaskTemplatesForSelect(
  params: ForSelectParams = {},
): Promise<PaginatedResponse<TaskTemplateForSelectItem>> {
  const response = await fetchForSelect(TASK_TEMPLATES_FOR_SELECT_RESOURCE, params)
  return { ...response, items: response.items as TaskTemplateForSelectItem[] }
}

interface UseTaskTemplatesForSelectOptions {
  search: string
  ids?: number[]
  enabled?: boolean
}

/**
 * Reusable hook feeding a task template single-select: debounced server
 * search, offset pagination and `ids[]` hydration, bound to the
 * `task-templates` resource. Consumed by the Commessa create form and the
 * Contract "Programma" dialog (MT-F3, out of this microtask's scope).
 */
export function useTaskTemplatesForSelect({ search, ids, enabled }: UseTaskTemplatesForSelectOptions) {
  return useForSelect({
    resource: TASK_TEMPLATES_FOR_SELECT_RESOURCE,
    search,
    ids,
    enabled,
  })
}
