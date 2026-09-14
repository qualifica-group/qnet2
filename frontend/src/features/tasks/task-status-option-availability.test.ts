import { describe, expect, it } from 'vitest'
import { isTaskStatusOptionDisabled } from '@/features/tasks/task-status-option-availability'
import type { TaskStatusForSelectItem } from '@/features/tasks/for-select-api'

function statusOption(overrides: Partial<TaskStatusForSelectItem['meta']> = {}): TaskStatusForSelectItem {
  return {
    id: 3,
    label: 'In lavorazione',
    meta: {
      system_key: 'open',
      group: 'open',
      completion_percentage: 25,
      color: 'blue',
      icon: null,
      ...overrides,
    },
  }
}

/** Spec 0123 D-4/D-5: pure mirror of which Stato options a PATCH could never reach. */
describe('isTaskStatusOptionDisabled', () => {
  it('disables in_validation regardless of close_via_status', () => {
    const option = statusOption({ group: 'in_validation' })
    expect(isTaskStatusOptionDisabled(option, true)).toBe(true)
    expect(isTaskStatusOptionDisabled(option, false)).toBe(true)
  })

  it('disables closed_positive regardless of close_via_status', () => {
    const option = statusOption({ group: 'closed_positive' })
    expect(isTaskStatusOptionDisabled(option, true)).toBe(true)
    expect(isTaskStatusOptionDisabled(option, false)).toBe(true)
  })

  it('disables closed_negative only when close_via_status is false', () => {
    const option = statusOption({ group: 'closed_negative' })
    expect(isTaskStatusOptionDisabled(option, false)).toBe(true)
    expect(isTaskStatusOptionDisabled(option, true)).toBe(false)
  })

  it('leaves open/pending selectable regardless of close_via_status', () => {
    expect(isTaskStatusOptionDisabled(statusOption({ group: 'open' }), false)).toBe(false)
    expect(isTaskStatusOptionDisabled(statusOption({ group: 'pending' }), false)).toBe(false)
  })

  it('never disables an option carrying no meta (route not yet registered)', () => {
    expect(isTaskStatusOptionDisabled({ id: 9, label: 'Senza meta' }, false)).toBe(false)
  })
})
