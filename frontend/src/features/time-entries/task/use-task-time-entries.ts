/**
 * Fetches the Task detail's Segnatempo section (`GET /api/tasks/{task}/time-entries`,
 * spec 0122 D-9). Shared by the collaboration tab's badge total and the tab's
 * own body (`TaskCollaborationSection` and `TaskTimeEntriesSection`) — same
 * query key, TanStack Query dedupes the request.
 */

import { useQuery } from '@tanstack/react-query'
import { fetchTaskTimeEntries } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'

export function useTaskTimeEntries(taskId: number, enabled: boolean) {
  return useQuery({
    queryKey: timeEntryKeys.taskEntries(taskId),
    queryFn: () => fetchTaskTimeEntries(taskId),
    enabled,
  })
}
