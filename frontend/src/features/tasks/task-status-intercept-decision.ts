/**
 * The pure classification half of `use-task-status-cell-intercept.tsx` (spec
 * 0156 D-8), split out so it is unit-testable without mocking the network.
 */
import type { TaskStatusGroupValue } from '@/features/status-reorder/types'

const CLOSING_GROUPS: TaskStatusGroupValue[] = ['closed_positive', 'closed_negative']

function isClosingGroup(group: TaskStatusGroupValue | undefined): boolean {
  return group !== undefined && CLOSING_GROUPS.includes(group)
}

/** What a `task_status` cell commit should trigger, given the row's OLD group and the picked status' NEW group. */
export type TaskStatusInterceptDecision = 'open_complete' | 'uncomplete' | null

/**
 * `open_complete`: the picked status closes the task and it was not closed
 * already — opens "Completa" instead of writing the id directly.
 * `uncomplete`: the task was closed and the picked status is not — calls
 * `uncomplete` directly.
 * `null`: every other transition (open<->open, or an already-closed row
 * staying closed, e.g. a super-admin override) — PATCHes normally.
 */
export function resolveTaskStatusInterceptDecision(
  oldGroup: TaskStatusGroupValue | undefined,
  newGroup: TaskStatusGroupValue | undefined,
): TaskStatusInterceptDecision {
  const wasClosed = isClosingGroup(oldGroup)
  const willClose = isClosingGroup(newGroup)

  if (willClose && !wasClosed) {
    return 'open_complete'
  }
  if (wasClosed && !willClose) {
    return 'uncomplete'
  }
  return null
}
