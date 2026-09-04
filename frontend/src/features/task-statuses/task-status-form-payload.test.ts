import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-statuses/task-status-form-payload'
import type { TaskStatusDetail } from '@/features/task-statuses/types'
import type { TaskStatusFormValues } from '@/features/task-statuses/use-task-status-form'

/**
 * Spec 0101 `data_contract`: `sort_order` and `system_key` are never accepted on
 * write, so they must never appear in either payload.
 */

const formValues: TaskStatusFormValues = {
  name: 'In progress',
  description: 'Follow-up on the client request',
  color: 'blue',
  icon: 'star',
  group: 'pending',
  is_active: true,
  completion_percentage: 25,
}

function original(overrides: Partial<TaskStatusDetail> = {}): TaskStatusDetail {
  return {
    id: 7,
    name: 'In progress',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    system_key: null,
    group: 'pending',
    completion_percentage: 25,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0101)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'In progress',
      color: 'blue',
      icon: 'star',
      description: 'Follow-up on the client request',
      group: 'pending',
      is_active: true,
      completion_percentage: 25,
    })
  })

  it('maps an unset icon onto null', () => {
    expect(buildCreatePayload({ ...formValues, icon: '' }).icon).toBeNull()
  })

  it('never carries sort_order or system_key', () => {
    const payload = buildCreatePayload(formValues)
    expect(payload).not.toHaveProperty('sort_order')
    expect(payload).not.toHaveProperty('system_key')
  })
})

describe('buildUpdatePayload (spec 0101)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Renamed' }, original())).toEqual({
      name: 'Renamed',
    })
  })

  it('clears the icon to null when the picker is emptied', () => {
    expect(buildUpdatePayload({ ...formValues, icon: '' }, original())).toEqual({ icon: null })
  })

  it('includes only the changed color', () => {
    expect(buildUpdatePayload({ ...formValues, color: 'emerald' }, original())).toEqual({
      color: 'emerald',
    })
  })

  it('includes only the changed group', () => {
    expect(buildUpdatePayload({ ...formValues, group: 'in_validation' }, original())).toEqual({
      group: 'in_validation',
    })
  })

  it('includes only the changed completion percentage (AC-044)', () => {
    expect(
      buildUpdatePayload({ ...formValues, completion_percentage: 100 }, original()),
    ).toEqual({ completion_percentage: 100 })
  })

  it('never carries sort_order or system_key, even on a fully changed form', () => {
    const payload = buildUpdatePayload(
      {
        ...formValues,
        name: 'Renamed',
        color: 'teal',
        icon: '',
        group: 'closed_positive',
        is_active: false,
        completion_percentage: 90,
      },
      original(),
    )
    expect(payload).not.toHaveProperty('sort_order')
    expect(payload).not.toHaveProperty('system_key')
  })
})
