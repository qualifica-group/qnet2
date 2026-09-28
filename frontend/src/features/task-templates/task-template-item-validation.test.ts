import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  extractItemServerErrors,
  validateTaskTemplateItemRows,
} from '@/features/task-templates/task-template-item-validation'
import type { TaskTemplateItemFormRow } from '@/features/task-templates/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function row(overrides: Partial<TaskTemplateItemFormRow> = {}): TaskTemplateItemFormRow {
  return {
    id: 'new-1',
    title: 'Kickoff call',
    description: null,
    estimated_minutes: null,
    task_status_id: null,
    due_offset_days: 0,
    stage_key: null,
    parent_key: null,
    ...overrides,
  }
}

describe('validateTaskTemplateItemRows (spec 0124)', () => {
  it('accepts a single valid row', () => {
    const result = validateTaskTemplateItemRows([row()], i18n.t)
    expect(result.formError).toBeNull()
    expect(result.errors).toEqual({})
  })

  it('reports a form-level error when there are no rows (min:1)', () => {
    const result = validateTaskTemplateItemRows([], i18n.t)
    expect(result.formError).toBe('Add at least one row.')
  })

  it('reports a per-row error for an empty title', () => {
    const result = validateTaskTemplateItemRows([row({ title: '  ' })], i18n.t)
    expect(result.errors['new-1'].title).toBe('Title is required.')
    expect(result.formError).toBe('Fix the highlighted rows before saving.')
  })

  it('reports a per-row error for a negative due_offset_days', () => {
    const result = validateTaskTemplateItemRows([row({ due_offset_days: -1 })], i18n.t)
    expect(result.errors['new-1'].due_offset_days).toBeDefined()
  })

  it('reports a per-row error for a due_offset_days over 3650', () => {
    const result = validateTaskTemplateItemRows([row({ due_offset_days: 3651 })], i18n.t)
    expect(result.errors['new-1'].due_offset_days).toBeDefined()
  })

  it('reports a per-row error for a negative estimated_minutes', () => {
    const result = validateTaskTemplateItemRows([row({ estimated_minutes: -5 })], i18n.t)
    expect(result.errors['new-1'].estimated_minutes).toBeDefined()
  })

  it('accepts a null estimated_minutes', () => {
    const result = validateTaskTemplateItemRows([row({ estimated_minutes: null })], i18n.t)
    expect(result.errors['new-1']).toBeUndefined()
  })

  /** Spec 0172 D-4/AC-006: a sub-item's due_offset_days can never exceed its direct parent's own. */
  it('reports a per-row error when a sub-item exceeds its parent due_offset_days', () => {
    const rows = [
      row({ id: 'root', due_offset_days: 3 }),
      row({ id: 'child', parent_key: 'root', due_offset_days: 4 }),
    ]
    const result = validateTaskTemplateItemRows(rows, i18n.t)
    expect(result.errors.child.due_offset_days).toBeDefined()
  })

  it('accepts a sub-item whose due_offset_days equals its parent', () => {
    const rows = [
      row({ id: 'root', due_offset_days: 3 }),
      row({ id: 'child', parent_key: 'root', due_offset_days: 3 }),
    ]
    const result = validateTaskTemplateItemRows(rows, i18n.t)
    expect(result.errors.child).toBeUndefined()
  })

  /** Spec 0172 D-1/AC-003: a 4th level below a root is invalid (the UI already hides the action that would create it). */
  it('reports a per-row parent_key error for a row past the 3rd level below a root', () => {
    const rows = [
      row({ id: 'root' }),
      row({ id: 'child', parent_key: 'root' }),
      row({ id: 'grandchild', parent_key: 'child' }),
      row({ id: 'great-grandchild', parent_key: 'grandchild' }),
      row({ id: 'too-deep', parent_key: 'great-grandchild' }),
    ]
    const result = validateTaskTemplateItemRows(rows, i18n.t)
    expect(result.errors['too-deep'].parent_key).toBeDefined()
  })
})

describe('extractItemServerErrors (spec 0124)', () => {
  it('maps a flat items.N.field key onto the row at that position', () => {
    const errors = extractItemServerErrors(
      { 'items.1.title': ['The items.1.title field is required.'] },
      ['row-a', 'row-b', 'row-c'],
    )
    expect(errors).toEqual({ 'row-b': { title: 'The items.1.title field is required.' } })
  })

  it('ignores keys outside the items.N.field shape', () => {
    const errors = extractItemServerErrors({ name: ['The name has already been taken.'] }, ['row-a'])
    expect(errors).toEqual({})
  })

  it('returns an empty map when errors is undefined', () => {
    expect(extractItemServerErrors(undefined, ['row-a'])).toEqual({})
  })
})
