import { describe, expect, it } from 'vitest'
import { buildQuickCreatePayload, type TaskQuickCreateFormValues } from '@/features/tasks/use-task-quick-create-row'

function values(overrides: Partial<TaskQuickCreateFormValues> = {}): TaskQuickCreateFormValues {
  return {
    title: 'Chiamare il cliente',
    task_type_id: null,
    task_priority_id: null,
    task_importance_id: null,
    task_status_id: null,
    end_date: '2026-09-24',
    requester_id: 1,
    assignee_ids: [1],
    watcher_ids: [],
    ...overrides,
  }
}

describe('buildQuickCreatePayload (spec 0156 D-7)', () => {
  it('builds the four required fields plus the classification/watcher ones', () => {
    expect(buildQuickCreatePayload(values())).toEqual({
      title: 'Chiamare il cliente',
      requester_id: 1,
      end_date: '2026-09-24',
      assignee_ids: [1],
      watcher_ids: [],
      task_type_id: null,
      task_priority_id: null,
      task_importance_id: null,
    })
  })

  it('trims the title', () => {
    expect(buildQuickCreatePayload(values({ title: '  Chiamare il cliente  ' })).title).toBe('Chiamare il cliente')
  })

  it('sends task_status_id only when the operator picked one', () => {
    expect(buildQuickCreatePayload(values())).not.toHaveProperty('task_status_id')
    expect(buildQuickCreatePayload(values({ task_status_id: 4 })).task_status_id).toBe(4)
  })
})
