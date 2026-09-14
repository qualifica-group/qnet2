/**
 * Query keys for the time entries module (spec 0122). Every key starts with
 * `'time-entries'`, so `queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })`
 * covers list/overview/pulse/team/detail/task in one call after any mutation
 * (create/update/delete/day-note).
 */

import type {
  TeamStatsParams,
  TimeEntriesFilterParams,
  TimeEntriesListParams,
} from '@/features/time-entries/types'

export const timeEntryKeys = {
  all: ['time-entries'] as const,
  list: (params: TimeEntriesListParams) => ['time-entries', 'list', params] as const,
  overview: (params: TimeEntriesFilterParams) => ['time-entries', 'overview', params] as const,
  pulse: (params: TimeEntriesFilterParams) => ['time-entries', 'pulse', params] as const,
  team: (params: TeamStatsParams) => ['time-entries', 'team', params] as const,
  detail: (id: number) => ['time-entries', 'detail', id] as const,
  /** Entries of a single Task, D-9 (`GET /api/tasks/{task}/time-entries`). */
  taskEntries: (taskId: number) => ['time-entries', 'task', taskId] as const,
}
