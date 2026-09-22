import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import {
  extractStageServerErrors,
  validateTaskTemplateStageRows,
} from '@/features/task-templates/task-template-stage-validation'
import type { TaskTemplateStageFormRow } from '@/features/task-templates/types'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function row(overrides: Partial<TaskTemplateStageFormRow> = {}): TaskTemplateStageFormRow {
  return { id: 'new-stage-1', name: 'Analisi', ...overrides }
}

describe('validateTaskTemplateStageRows (spec 0146 D-2)', () => {
  it('accepts an empty array — a template may have no fasi at all', () => {
    const result = validateTaskTemplateStageRows([], i18n.t)
    expect(result.formError).toBeNull()
    expect(result.errors).toEqual({})
  })

  it('accepts a single valid row', () => {
    const result = validateTaskTemplateStageRows([row()], i18n.t)
    expect(result.formError).toBeNull()
  })

  it('reports a per-row error for an empty name', () => {
    const result = validateTaskTemplateStageRows([row({ name: '  ' })], i18n.t)
    expect(result.errors['new-stage-1'].name).toBeDefined()
    expect(result.formError).toBe('Fix the highlighted rows before saving.')
  })

  it('reports a per-row error for a name over 191 characters', () => {
    const result = validateTaskTemplateStageRows([row({ name: 'a'.repeat(192) })], i18n.t)
    expect(result.errors['new-stage-1'].name).toBeDefined()
  })

  it('reports a form-level error over 50 rows', () => {
    const rows = Array.from({ length: 51 }, (_unused, index) => row({ id: `stage-${index}`, name: `Fase ${index}` }))
    const result = validateTaskTemplateStageRows(rows, i18n.t)
    expect(result.formError).toBe('You can add at most 50 rows.')
  })
})

describe('extractStageServerErrors (spec 0146 D-2)', () => {
  it('maps a flat stages.N.field key onto the row at that position', () => {
    const errors = extractStageServerErrors({ 'stages.1.name': ['The stages.1.name field is required.'] }, [
      'stage-a',
      'stage-b',
    ])
    expect(errors).toEqual({ 'stage-b': { name: 'The stages.1.name field is required.' } })
  })

  it('maps a stages.N.id error onto the "id" field', () => {
    const errors = extractStageServerErrors(
      { 'stages.0.id': ['This stage does not belong to this task template.'] },
      ['stage-a'],
    )
    expect(errors).toEqual({ 'stage-a': { id: 'This stage does not belong to this task template.' } })
  })

  it('returns an empty map when errors is undefined', () => {
    expect(extractStageServerErrors(undefined, ['stage-a'])).toEqual({})
  })
})
