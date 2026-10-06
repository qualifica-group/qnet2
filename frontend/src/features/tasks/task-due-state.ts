import type { DueState } from '@/components/due-date-chip'
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

const CLOSED_GROUPS: ReadonlySet<string> = new Set<TaskStatusGroupValue>(['closed_positive', 'closed_negative'])

/**
 * Where a task's "Data fine" stands (user directive 2026-10-06, "come in
 * commesse"): the commessa board's rule (`task-board-metrics.ts`) on the end
 * date alone — a closed task is never late; an open one is overdue once the
 * date has passed and "today" on the day itself. `today` is `Y-m-d`.
 */
export function taskEndDateState(
  endDate: string | null,
  statusGroup: TaskStatusGroupValue | null | undefined,
  today: string,
): DueState {
  if (endDate === null || (statusGroup && CLOSED_GROUPS.has(statusGroup))) {
    return null
  }
  if (endDate < today) {
    return 'overdue'
  }
  return endDate === today ? 'today' : null
}
