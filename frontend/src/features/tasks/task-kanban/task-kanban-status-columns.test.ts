import { describe, expect, it } from 'vitest'
import {
  buildTaskStatusKanbanGroups,
  isManualStatusColumn,
} from '@/features/tasks/task-kanban/task-kanban-status-columns'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'
import type { TaskKanbanRow } from '@/features/tasks/task-kanban/task-kanban-types'

function status(id: number, label: string, group: TaskStatusForSelectItem['meta']['group']): TaskStatusForSelectItem {
  return {
    id,
    label,
    meta: { system_key: null, group, completion_percentage: 0, color: 'blue', icon: null },
  }
}

function row(id: number, statusId: number): TaskKanbanRow {
  return {
    id,
    actions: [],
    title: `Task ${id}`,
    task_status: { id: statusId, name: 'x', color: 'blue', icon: null, group: 'open' },
    task_priority: null,
    start_date: null,
    end_date: null,
    completion_percentage: 0,
    estimated_minutes: null,
    actual_minutes: 0,
    is_blocked: false,
    assignees: [],
    has_subtasks: false,
  }
}

describe('buildTaskStatusKanbanGroups', () => {
  it('builds one column per status, in catalog order, filled with matching rows', () => {
    const statuses = [status(1, 'Aperto', 'open'), status(2, 'In corso', 'open'), status(3, 'Chiuso', 'closed_positive')]
    const rows = [row(10, 1), row(11, 2), row(12, 1)]

    const groups = buildTaskStatusKanbanGroups(statuses, rows)

    expect(groups.map((g) => g.key)).toEqual(['1', '2', '3'])
    expect(groups[0].rows.map((r) => r.id)).toEqual([10, 12])
    expect(groups[1].rows.map((r) => r.id)).toEqual([11])
    expect(groups[2].rows).toEqual([])
  })

  it('every status column stays droppable/draggable (D-3 decides on the transition, not the column)', () => {
    const groups = buildTaskStatusKanbanGroups([status(1, 'Aperto', 'open')], [])
    expect(groups[0].droppable).toBe(true)
    expect(groups[0].draggable).toBe(true)
  })
})

describe('isManualStatusColumn', () => {
  it('accepts only open/pending', () => {
    expect(isManualStatusColumn('open')).toBe(true)
    expect(isManualStatusColumn('pending')).toBe(true)
    expect(isManualStatusColumn('in_validation')).toBe(false)
    expect(isManualStatusColumn('closed_positive')).toBe(false)
    expect(isManualStatusColumn('closed_negative')).toBe(false)
  })
})
