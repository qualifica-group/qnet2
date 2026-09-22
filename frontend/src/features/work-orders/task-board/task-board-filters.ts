/**
 * Pure filtering/grouping functions for the Task board (D-6), independent of
 * any component: everything here takes the board's own `tasks`/`stages`
 * arrays and returns a derived value, so AC-025's filter behaviour is
 * unit-testable without mounting a single component.
 */

import type {
  BoardTask,
  TaskBoardAssignmentFilter,
  TaskBoardFilters,
  WorkOrderStage,
} from '@/features/work-orders/task-board/types'
import type { TaskLookupRef, TaskNamedRef } from '@/features/tasks/types'

/** "Aperti" is the predefined status filter (D-6): every other axis starts unrestricted. */
export const DEFAULT_TASK_BOARD_FILTERS: TaskBoardFilters = {
  search: '',
  taskTypeIds: [],
  due: 'all',
  status: 'open',
  assignment: 'all',
  requesterIds: [],
  assigneeIds: [],
  watcherIds: [],
  taskPriorityIds: [],
  taskStatusIds: [],
  taskImportanceIds: [],
}

function isRoot(task: BoardTask): boolean {
  return task.parent_task_id === null
}

function isClosedTask(task: BoardTask): boolean {
  return task.task_status.group === 'closed_positive' || task.task_status.group === 'closed_negative'
}

/** `end_date ?? start_date` (D-6's own due reference). */
function dueReference(task: BoardTask): string | null {
  return task.end_date ?? task.start_date
}

/**
 * Parses a `Y-m-d` string as a UTC midnight instant and formats it back the
 * same way: every step below stays in UTC on purpose, so the result never
 * shifts by a day depending on the runner's local timezone (a plain `new
 * Date(str)` + `toISOString()` round-trip is NOT timezone-safe).
 */
function parseUtcDate(date: string): Date {
  const [year, month, day] = date.split('-').map(Number)
  return new Date(Date.UTC(year, month - 1, day))
}

function formatUtcDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

/** Monday of the ISO week `date` (`Y-m-d`) falls in, as `Y-m-d`. */
function startOfIsoWeek(date: string): string {
  const parsed = parseUtcDate(date)
  const isoWeekday = parsed.getUTCDay() === 0 ? 7 : parsed.getUTCDay()
  parsed.setUTCDate(parsed.getUTCDate() - (isoWeekday - 1))
  return formatUtcDate(parsed)
}

/** Sunday of the ISO week `date` falls in, as `Y-m-d`. */
function endOfIsoWeek(date: string): string {
  const parsed = parseUtcDate(startOfIsoWeek(date))
  parsed.setUTCDate(parsed.getUTCDate() + 6)
  return formatUtcDate(parsed)
}

function matchesDue(task: BoardTask, due: TaskBoardFilters['due'], today: string): boolean {
  if (due === 'all') {
    return true
  }
  const reference = dueReference(task)
  if (reference === null) {
    return false
  }
  if (due === 'today') {
    return reference === today
  }
  if (due === 'overdue') {
    return reference < today
  }
  return reference >= startOfIsoWeek(today) && reference <= endOfIsoWeek(today)
}

function matchesStatus(task: BoardTask, status: TaskBoardFilters['status']): boolean {
  if (status === 'all') {
    return true
  }
  if (status === 'blocked') {
    return task.is_blocked
  }
  if (status === 'completed') {
    return isClosedTask(task)
  }
  return task.task_status.group === 'open' || task.task_status.group === 'pending' || task.task_status.group === 'in_validation'
}

function matchesAssignment(task: BoardTask, assignment: TaskBoardAssignmentFilter, currentUserId: number): boolean {
  if (assignment === 'all') {
    return true
  }
  if (assignment === 'assigned_to_me') {
    return task.assignees.some((assignee) => assignee.id === currentUserId)
  }
  return task.requester?.id === currentUserId
}

function matchesIdSet(id: number | null | undefined, ids: number[]): boolean {
  return ids.length === 0 || (id !== null && id !== undefined && ids.includes(id))
}

function matchesAnyIdSet(refs: TaskNamedRef[], ids: number[]): boolean {
  return ids.length === 0 || refs.some((ref) => ids.includes(ref.id))
}

/** Every D-6 axis, ANDed together — evaluated on a ROOT task only. */
function matchesBoardFilters(
  task: BoardTask,
  filters: TaskBoardFilters,
  currentUserId: number,
  today: string,
): boolean {
  const search = filters.search.trim().toLowerCase()
  return (
    (search === '' || task.title.toLowerCase().includes(search)) &&
    matchesIdSet(task.task_type?.id, filters.taskTypeIds) &&
    matchesDue(task, filters.due, today) &&
    matchesStatus(task, filters.status) &&
    matchesAssignment(task, filters.assignment, currentUserId) &&
    matchesIdSet(task.requester?.id, filters.requesterIds) &&
    matchesAnyIdSet(task.assignees, filters.assigneeIds) &&
    matchesAnyIdSet(task.watchers, filters.watcherIds) &&
    matchesIdSet(task.task_priority?.id, filters.taskPriorityIds) &&
    matchesIdSet(task.task_status.id, filters.taskStatusIds) &&
    matchesIdSet(task.task_importance?.id, filters.taskImportanceIds)
  )
}

/** Every descendant (any depth) of `rootId`, plus `rootId` itself, added to `keptIds`. */
function collectSubtree(rootId: number, childrenByParentId: Map<number, BoardTask[]>, keptIds: Set<number>): void {
  keptIds.add(rootId)
  for (const child of childrenByParentId.get(rootId) ?? []) {
    collectSubtree(child.id, childrenByParentId, keptIds)
  }
}

function groupChildrenByParent(tasks: BoardTask[]): Map<number, BoardTask[]> {
  const map = new Map<number, BoardTask[]>()
  for (const task of tasks) {
    if (task.parent_task_id === null) {
      continue
    }
    const siblings = map.get(task.parent_task_id) ?? []
    siblings.push(task)
    map.set(task.parent_task_id, siblings)
  }
  return map
}

/**
 * D-6: the filter applies to ROOT tasks only; a sub-task is kept whenever its
 * root ancestor passes, regardless of the sub-task's own fields — mirroring
 * the legacy behaviour.
 */
export function filterBoardTasks(
  tasks: BoardTask[],
  filters: TaskBoardFilters,
  currentUserId: number,
  today: string,
): BoardTask[] {
  const passingRootIds = tasks.filter(isRoot).filter((root) => matchesBoardFilters(root, filters, currentUserId, today))
  const childrenByParentId = groupChildrenByParent(tasks)

  const keptIds = new Set<number>()
  for (const root of passingRootIds) {
    collectSubtree(root.id, childrenByParentId, keptIds)
  }

  return tasks.filter((task) => keptIds.has(task.id))
}

/** One node of the parent/children tree built by `buildBoardTree`. */
export interface BoardTaskNode {
  task: BoardTask
  children: BoardTaskNode[]
}

function toNode(task: BoardTask, childrenByParentId: Map<number, BoardTask[]>): BoardTaskNode {
  const children = (childrenByParentId.get(task.id) ?? []).map((child) => toNode(child, childrenByParentId))
  return { task, children }
}

/** Nests every sub-task (any depth) under its parent, roots first. */
export function buildBoardTree(tasks: BoardTask[]): BoardTaskNode[] {
  const childrenByParentId = groupChildrenByParent(tasks)
  return tasks.filter(isRoot).map((root) => toNode(root, childrenByParentId))
}

/** One column/section of the board: a fase (or `null` = "Senza fase") and its root nodes, in `stage_position`. */
export interface BoardStageGroup {
  stage: WorkOrderStage | null
  roots: BoardTaskNode[]
}

/** Fasi in `sort_order`, plus a trailing "Senza fase" group; each group's roots sorted by `stage_position`. */
export function groupRootsByStage(stages: WorkOrderStage[], roots: BoardTaskNode[]): BoardStageGroup[] {
  const byStageId = new Map<number, BoardTaskNode[]>()
  const noStage: BoardTaskNode[] = []

  for (const node of roots) {
    const stageId = node.task.work_order_stage_id
    if (stageId === null) {
      noStage.push(node)
      continue
    }
    const bucket = byStageId.get(stageId) ?? []
    bucket.push(node)
    byStageId.set(stageId, bucket)
  }

  const byPosition = (nodes: BoardTaskNode[]) => [...nodes].sort((a, b) => a.task.stage_position - b.task.stage_position)

  const groups: BoardStageGroup[] = [...stages]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((stage) => ({ stage, roots: byPosition(byStageId.get(stage.id) ?? []) }))

  groups.push({ stage: null, roots: byPosition(noStage) })
  return groups
}

/** Distinct requester/assignee/watcher/type/priority options, derived from the board's own root tasks, name-sorted. */
export interface TaskBoardFilterOptions {
  requesters: TaskNamedRef[]
  assignees: TaskNamedRef[]
  watchers: TaskNamedRef[]
  taskTypes: TaskLookupRef[]
  taskPriorities: TaskLookupRef[]
  taskStatuses: TaskLookupRef[]
  taskImportances: TaskLookupRef[]
}

function byName<T extends { name: string }>(a: T, b: T): number {
  return a.name.localeCompare(b.name)
}

export function deriveTaskBoardFilterOptions(tasks: BoardTask[]): TaskBoardFilterOptions {
  const requesters = new Map<number, TaskNamedRef>()
  const assignees = new Map<number, TaskNamedRef>()
  const watchers = new Map<number, TaskNamedRef>()
  const taskTypes = new Map<number, TaskLookupRef>()
  const taskPriorities = new Map<number, TaskLookupRef>()
  const taskStatuses = new Map<number, TaskLookupRef>()
  const taskImportances = new Map<number, TaskLookupRef>()

  for (const task of tasks.filter(isRoot)) {
    if (task.requester) {
      requesters.set(task.requester.id, task.requester)
    }
    for (const assignee of task.assignees) {
      assignees.set(assignee.id, assignee)
    }
    for (const watcher of task.watchers) {
      watchers.set(watcher.id, watcher)
    }
    if (task.task_type) {
      taskTypes.set(task.task_type.id, task.task_type)
    }
    if (task.task_priority) {
      taskPriorities.set(task.task_priority.id, task.task_priority)
    }
    taskStatuses.set(task.task_status.id, task.task_status)
    if (task.task_importance) {
      taskImportances.set(task.task_importance.id, task.task_importance)
    }
  }

  return {
    requesters: [...requesters.values()].sort(byName),
    assignees: [...assignees.values()].sort(byName),
    watchers: [...watchers.values()].sort(byName),
    taskTypes: [...taskTypes.values()].sort(byName),
    taskPriorities: [...taskPriorities.values()].sort(byName),
    taskStatuses: [...taskStatuses.values()].sort(byName),
    taskImportances: [...taskImportances.values()].sort(byName),
  }
}
