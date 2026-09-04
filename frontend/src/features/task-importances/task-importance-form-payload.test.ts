import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/task-importances/task-importance-form-payload'
import type { TaskImportanceDetail } from '@/features/task-importances/types'
import type { TaskImportanceFormValues } from '@/features/task-importances/use-task-importance-form'

/**
 * Spec 0101 `data_contract`: `sort_order` is never accepted on
 * write, so it must never appear in either payload.
 */

const formValues: TaskImportanceFormValues = {
  name: 'Critical',
  description: 'Follow-up on the client request',
  color: 'blue',
  icon: 'star',
  is_active: true,
}

function original(overrides: Partial<TaskImportanceDetail> = {}): TaskImportanceDetail {
  return {
    id: 7,
    name: 'Critical',
    description: 'Follow-up on the client request',
    color: 'blue',
    icon: 'star',
    sort_order: 3,
    is_active: true,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0101)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Critical',
      color: 'blue',
      icon: 'star',
      description: 'Follow-up on the client request',
      is_active: true,
    })
  })

  it('maps an unset icon onto null', () => {
    expect(buildCreatePayload({ ...formValues, icon: '' }).icon).toBeNull()
  })

  it('never carries sort_order', () => {
    const payload = buildCreatePayload(formValues)
    expect(payload).not.toHaveProperty('sort_order')
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

  it('never carries sort_order, even on a fully changed form', () => {
    const payload = buildUpdatePayload(
      { ...formValues, name: 'Renamed', color: 'teal', icon: '', is_active: false },
      original(),
    )
    expect(payload).not.toHaveProperty('sort_order')
  })
})
