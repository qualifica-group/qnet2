import type { ActivityLogEventFilter } from '@/features/activity-log/types'

/** Centralized TanStack Query keys for the aggregated activity log (spec 0034). */
export const activityLogKeys = {
  list: (resource: string, id: number, event: ActivityLogEventFilter) =>
    ['activity-log', resource, id, event] as const,
}
