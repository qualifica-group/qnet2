import { describe, expect, it } from 'vitest'
import { boardTask, workOrderStage } from '@/features/work-orders/task-board/task-board-fixtures'
import {
  DEFAULT_TASK_BOARD_FILTERS,
  buildBoardTree,
  deriveTaskBoardFilterOptions,
  filterBoardTasks,
  groupRootsByStage,
} from '@/features/work-orders/task-board/task-board-filters'

const TODAY = '2026-09-22'
const CURRENT_USER_ID = 21

describe('DEFAULT_TASK_BOARD_FILTERS', () => {
  it('defaults status to "open" and every other axis unrestricted (D-6)', () => {
    expect(DEFAULT_TASK_BOARD_FILTERS.status).toBe('open')
    expect(DEFAULT_TASK_BOARD_FILTERS.search).toBe('')
    expect(DEFAULT_TASK_BOARD_FILTERS.taskTypeIds).toEqual([])
  })
})

describe('filterBoardTasks (AC-025)', () => {
  it('filters roots by title, case-insensitively, keeping their sub-tasks regardless of the sub-task title', () => {
    const root = boardTask({ id: 1, title: 'Verificare impianto', parent_task_id: null })
    const child = boardTask({ id: 2, title: 'Altro', parent_task_id: 1, work_order_stage_id: null })
    const other = boardTask({ id: 3, title: 'Contattare cliente', parent_task_id: null })

    const result = filterBoardTasks(
      [root, child, other],
      { ...DEFAULT_TASK_BOARD_FILTERS, search: 'IMPIANTO' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id).sort()).toEqual([1, 2])
  })

  it('keeps a whole sub-tree (grandchildren included) when the root passes', () => {
    const root = boardTask({ id: 1, parent_task_id: null })
    const child = boardTask({ id: 2, parent_task_id: 1, work_order_stage_id: null })
    const grandchild = boardTask({ id: 3, parent_task_id: 2, work_order_stage_id: null })

    const result = filterBoardTasks([root, child, grandchild], DEFAULT_TASK_BOARD_FILTERS, CURRENT_USER_ID, TODAY)

    expect(result.map((task) => task.id).sort()).toEqual([1, 2, 3])
  })

  it('filters by task type', () => {
    const matching = boardTask({ id: 1, task_type: { id: 5, name: 'Sopralluogo', color: 'blue', icon: null } })
    const other = boardTask({ id: 2, task_type: { id: 6, name: 'Manutenzione', color: 'red', icon: null } })

    const result = filterBoardTasks(
      [matching, other],
      { ...DEFAULT_TASK_BOARD_FILTERS, taskTypeIds: [5] },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('due filter "today" keeps only end_date === today, falling back to start_date when unset', () => {
    const dueToday = boardTask({ id: 1, end_date: TODAY, start_date: null })
    const dueTomorrow = boardTask({ id: 2, end_date: '2026-09-23', start_date: null })
    const startsToday = boardTask({ id: 3, end_date: null, start_date: TODAY })

    const result = filterBoardTasks(
      [dueToday, dueTomorrow, startsToday],
      { ...DEFAULT_TASK_BOARD_FILTERS, due: 'today' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id).sort()).toEqual([1, 3])
  })

  it('due filter "overdue" keeps only a reference strictly before today', () => {
    const overdue = boardTask({ id: 1, end_date: '2026-09-21', start_date: null })
    const today = boardTask({ id: 2, end_date: TODAY, start_date: null })
    const noDate = boardTask({ id: 3, end_date: null, start_date: null })

    const result = filterBoardTasks(
      [overdue, today, noDate],
      { ...DEFAULT_TASK_BOARD_FILTERS, due: 'overdue' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('due filter "this_week" keeps a reference inside the ISO week containing today', () => {
    // 2026-09-22 is a Tuesday: the ISO week runs 2026-09-21..2026-09-27.
    const inWeek = boardTask({ id: 1, end_date: '2026-09-27', start_date: null })
    const outOfWeek = boardTask({ id: 2, end_date: '2026-09-28', start_date: null })

    const result = filterBoardTasks(
      [inWeek, outOfWeek],
      { ...DEFAULT_TASK_BOARD_FILTERS, due: 'this_week' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('status filter "open" keeps open/pending/in_validation, excludes closed and blocked-only rows are unaffected', () => {
    const open = boardTask({ id: 1, task_status: { id: 1, name: 'Aperto', color: 'blue', icon: null, system_key: 'open', group: 'open', completion_percentage: 0 } })
    const closed = boardTask({ id: 2, task_status: { id: 2, name: 'Chiuso', color: 'green', icon: null, system_key: 'closed_positive', group: 'closed_positive', completion_percentage: 100 } })

    const result = filterBoardTasks([open, closed], DEFAULT_TASK_BOARD_FILTERS, CURRENT_USER_ID, TODAY)

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('status filter "completed" keeps only closed_positive/closed_negative', () => {
    const closedPositive = boardTask({ id: 1, task_status: { id: 2, name: 'Chiuso', color: 'green', icon: null, system_key: 'closed_positive', group: 'closed_positive', completion_percentage: 100 } })
    const open = boardTask({ id: 2 })

    const result = filterBoardTasks(
      [closedPositive, open],
      { ...DEFAULT_TASK_BOARD_FILTERS, status: 'completed' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('status filter "blocked" keeps only is_blocked tasks', () => {
    const blocked = boardTask({ id: 1, is_blocked: true })
    const notBlocked = boardTask({ id: 2, is_blocked: false })

    const result = filterBoardTasks(
      [blocked, notBlocked],
      { ...DEFAULT_TASK_BOARD_FILTERS, status: 'blocked' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('status filter "all" keeps every task regardless of phase', () => {
    const open = boardTask({ id: 1 })
    const closed = boardTask({ id: 2, task_status: { id: 2, name: 'Chiuso', color: 'green', icon: null, system_key: 'closed_positive', group: 'closed_positive', completion_percentage: 100 } })

    const result = filterBoardTasks(
      [open, closed],
      { ...DEFAULT_TASK_BOARD_FILTERS, status: 'all' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id).sort()).toEqual([1, 2])
  })

  it('assignment filter "assigned_to_me" keeps tasks where the current user is an assignee', () => {
    const mine = boardTask({ id: 1, assignees: [{ id: CURRENT_USER_ID, name: 'Me' }] })
    const other = boardTask({ id: 2, assignees: [{ id: 99, name: 'Altro' }] })

    const result = filterBoardTasks(
      [mine, other],
      { ...DEFAULT_TASK_BOARD_FILTERS, assignment: 'assigned_to_me' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('assignment filter "requested_by_me" keeps tasks where the current user is the requester', () => {
    const mine = boardTask({ id: 1, requester: { id: CURRENT_USER_ID, name: 'Me' } })
    const other = boardTask({ id: 2, requester: { id: 99, name: 'Altro' } })

    const result = filterBoardTasks(
      [mine, other],
      { ...DEFAULT_TASK_BOARD_FILTERS, assignment: 'requested_by_me' },
      CURRENT_USER_ID,
      TODAY,
    )

    expect(result.map((task) => task.id)).toEqual([1])
  })

  it('filters by requester, assignee, watcher and priority id sets', () => {
    const matching = boardTask({
      id: 1,
      requester: { id: 21, name: 'Bruno' },
      assignees: [{ id: 31, name: 'Dario' }],
      watchers: [{ id: 41, name: 'Fabio' }],
      task_priority: { id: 9, name: 'Alta', color: 'red', icon: null },
    })
    const other = boardTask({
      id: 2,
      requester: { id: 22, name: 'Carla' },
      assignees: [{ id: 32, name: 'Elsa' }],
      watchers: [{ id: 42, name: 'Gino' }],
      task_priority: { id: 8, name: 'Bassa', color: 'blue', icon: null },
    })

    expect(
      filterBoardTasks([matching, other], { ...DEFAULT_TASK_BOARD_FILTERS, requesterIds: [21] }, CURRENT_USER_ID, TODAY).map((task) => task.id),
    ).toEqual([1])
    expect(
      filterBoardTasks([matching, other], { ...DEFAULT_TASK_BOARD_FILTERS, assigneeIds: [31] }, CURRENT_USER_ID, TODAY).map((task) => task.id),
    ).toEqual([1])
    expect(
      filterBoardTasks([matching, other], { ...DEFAULT_TASK_BOARD_FILTERS, watcherIds: [41] }, CURRENT_USER_ID, TODAY).map((task) => task.id),
    ).toEqual([1])
    expect(
      filterBoardTasks([matching, other], { ...DEFAULT_TASK_BOARD_FILTERS, taskPriorityIds: [9] }, CURRENT_USER_ID, TODAY).map((task) => task.id),
    ).toEqual([1])
  })
})

describe('buildBoardTree', () => {
  it('nests sub-tasks (any depth) under their parent, roots first', () => {
    const root = boardTask({ id: 1, parent_task_id: null })
    const child = boardTask({ id: 2, parent_task_id: 1, work_order_stage_id: null })
    const grandchild = boardTask({ id: 3, parent_task_id: 2, work_order_stage_id: null })

    const tree = buildBoardTree([root, child, grandchild])

    expect(tree).toHaveLength(1)
    expect(tree[0].task.id).toBe(1)
    expect(tree[0].children).toHaveLength(1)
    expect(tree[0].children[0].task.id).toBe(2)
    expect(tree[0].children[0].children[0].task.id).toBe(3)
  })
})

describe('groupRootsByStage', () => {
  it('orders groups by sort_order, appends a trailing "Senza fase" group, and sorts roots by stage_position', () => {
    const stageB = workOrderStage({ id: 2, name: 'B', sort_order: 1 })
    const stageA = workOrderStage({ id: 1, name: 'A', sort_order: 0 })
    const rootA1 = boardTask({ id: 1, work_order_stage_id: 1, stage_position: 1 })
    const rootA0 = boardTask({ id: 2, work_order_stage_id: 1, stage_position: 0 })
    const rootB = boardTask({ id: 3, work_order_stage_id: 2, stage_position: 0 })
    const noStage = boardTask({ id: 4, work_order_stage_id: null, stage_position: 0 })

    const tree = buildBoardTree([rootA1, rootA0, rootB, noStage])
    const groups = groupRootsByStage([stageB, stageA], tree)

    expect(groups.map((group) => group.stage?.id ?? null)).toEqual([1, 2, null])
    expect(groups[0].roots.map((node) => node.task.id)).toEqual([2, 1])
    expect(groups[1].roots.map((node) => node.task.id)).toEqual([3])
    expect(groups[2].roots.map((node) => node.task.id)).toEqual([4])
  })
})

describe('deriveTaskBoardFilterOptions', () => {
  it('deduplicates by id and name-sorts each axis, derived from root tasks only', () => {
    const root1 = boardTask({
      id: 1,
      parent_task_id: null,
      requester: { id: 2, name: 'Zeta' },
      assignees: [{ id: 31, name: 'Dario' }],
      watchers: [{ id: 41, name: 'Fabio' }],
      task_type: { id: 5, name: 'Sopralluogo', color: 'blue', icon: null },
      task_priority: { id: 9, name: 'Alta', color: 'red', icon: null },
    })
    const root2 = boardTask({
      id: 2,
      parent_task_id: null,
      requester: { id: 1, name: 'Ada' },
      assignees: [{ id: 31, name: 'Dario' }],
      watchers: [],
      task_type: null,
      task_priority: null,
    })
    const subtask = boardTask({
      id: 3,
      parent_task_id: 1,
      work_order_stage_id: null,
      requester: { id: 999, name: 'Escluso' },
    })

    const options = deriveTaskBoardFilterOptions([root1, root2, subtask])

    expect(options.requesters.map((ref) => ref.name)).toEqual(['Ada', 'Zeta'])
    expect(options.assignees).toEqual([{ id: 31, name: 'Dario' }])
    expect(options.watchers).toEqual([{ id: 41, name: 'Fabio' }])
    expect(options.taskTypes.map((ref) => ref.id)).toEqual([5])
    expect(options.taskPriorities.map((ref) => ref.id)).toEqual([9])
  })
})
