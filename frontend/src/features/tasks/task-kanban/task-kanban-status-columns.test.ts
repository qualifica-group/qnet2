import { describe, expect, it } from 'vitest'
import {
  buildTaskStatusKanbanGroups,
  isManualStatusColumn,
} from '@/features/tasks/task-kanban/task-kanban-status-columns'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'

function status(id: number, label: string, group: TaskStatusForSelectItem['meta']['group']): TaskStatusForSelectItem {
  return {
    id,
    label,
    meta: { system_key: null, group, completion_percentage: 0, color: 'blue', icon: null },
  }
}

describe('buildTaskStatusKanbanGroups (spec 0164 D-1/D-2: metadata only, rows load per column)', () => {
  it('builds one column per status, in catalog order, each carrying its own `kanbanGroup` param', () => {
    const statuses = [status(1, 'Aperto', 'open'), status(2, 'In corso', 'open'), status(3, 'Chiuso', 'closed_positive')]

    const groups = buildTaskStatusKanbanGroups(statuses)

    expect(groups.map((g) => g.key)).toEqual(['1', '2', '3'])
    expect(groups.map((g) => g.kanbanGroup)).toEqual([
      { by: 'status', key: 1 },
      { by: 'status', key: 2 },
      { by: 'status', key: 3 },
    ])
  })

  it('every status column stays droppable/draggable (D-3 decides on the transition, not the column)', () => {
    const groups = buildTaskStatusKanbanGroups([status(1, 'Aperto', 'open')])
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
