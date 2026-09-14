/**
 * Task type options for the entries table's inline "change type" dropdown
 * (spec 0122 D-14). The catalogue is small and admin-curated (context:
 * `task_types`), so a single page covers it — no search/infinite scroll
 * needed, unlike the drawer's `for-select-multi` filters.
 */

import { useQuery } from '@tanstack/react-query'
import {
  fetchTaskTypesForSelect,
  type TaskTypeForSelectItem,
} from '@/features/task-types/for-select-api'

/** Comfortably above the seeded catalogue (Attivita', Riunione, ...) plus admin additions. */
const TASK_TYPE_OPTIONS_LIMIT = 100

export function useTimeEntryTaskTypeOptions(enabled: boolean) {
  const query = useQuery({
    queryKey: ['time-entries', 'task-type-options'],
    queryFn: () => fetchTaskTypesForSelect({ limit: TASK_TYPE_OPTIONS_LIMIT }),
    enabled,
    staleTime: 5 * 60 * 1000,
  })

  const options: TaskTypeForSelectItem[] = query.data?.items ?? []
  return { options, isLoading: query.isLoading }
}
