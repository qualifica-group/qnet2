import { describe, expect, it } from 'vitest'
import {
  buildChecklistPayload,
  buildDefaultChecklistValues,
  buildDefaultItem,
  checklistToFormValues,
} from '@/features/document-checklists/document-checklist-form-values'
import type { DocumentChecklist } from '@/features/document-checklists/types'

const CHECKLIST: DocumentChecklist = {
  id: 7,
  name: 'Opening',
  code: 'OPEN',
  description: null,
  category: { id: 4, name: 'Mortgages' },
  is_active: false,
  code_locked: true,
  sync_status: 'synced',
  sync_error: null,
  synced_at: null,
  sections: [
    { id: 3, name: 'Identity', description: 'Personal documents' },
    { id: 4, name: 'Property', description: null },
  ],
  items: [
    {
      id: 11,
      name: 'ID card',
      description: null,
      section_index: 1,
      date_field: null,
      is_required: true,
      allowed_file_types: ['pdf', 'jpg'],
      max_file_size_kb: 2048,
      min_files: 1,
      max_files: 3,
      validation_prompt: null,
      max_age_days: 90,
    },
  ],
  created_at: '2026-11-23T10:00:00Z',
  updated_at: '2026-11-23T10:00:00Z',
}

describe('document checklist form values (spec 0403)', () => {
  it('starts a new item with the spec defaults', () => {
    expect(buildDefaultItem()).toMatchObject({
      server_id: null,
      name: '',
      is_required: true,
      allowed_file_types: ['pdf'],
      max_file_size_kb: 10240,
      allow_multiple: false,
      section_key: '',
      date_field: '',
      max_age_days: null,
    })
  })

  it('starts a new checklist active and empty', () => {
    expect(buildDefaultChecklistValues()).toEqual({
      name: '',
      code: '',
      description: '',
      category_id: null,
      is_active: true,
      sections: [],
      items: [],
    })
  })

  it('maps a saved checklist to form values keeping the entry id as server_id', () => {
    const values = checklistToFormValues(CHECKLIST)

    expect(values.description).toBe('')
    expect(values.items[0]).toMatchObject({ server_id: 11, validation_prompt: '', max_age_days: 90, date_field: '' })
  })

  it('maps the category to category_id and back to the payload, null when absent (spec 0410)', () => {
    expect(checklistToFormValues(CHECKLIST).category_id).toBe(4)
    expect(buildChecklistPayload(checklistToFormValues(CHECKLIST)).category_id).toBe(4)

    const uncategorized = checklistToFormValues({ ...CHECKLIST, category: null })
    expect(uncategorized.category_id).toBeNull()
    expect(buildChecklistPayload(uncategorized).category_id).toBeNull()
  })

  it('builds a payload with null for empty optional text and the entry id', () => {
    const payload = buildChecklistPayload(checklistToFormValues(CHECKLIST))

    expect(payload.description).toBeNull()
    expect(payload.items[0]).toMatchObject({ id: 11, description: null, validation_prompt: null, section_index: 1, date_field: null })
    expect(payload).not.toHaveProperty('code_locked')
  })

  it('keeps the displayed order and sends id null for new entries', () => {
    const values = checklistToFormValues(CHECKLIST)
    values.items = [{ ...buildDefaultItem(), name: ' New ' }, values.items[0]]

    const payload = buildChecklistPayload(values)

    expect(payload.items.map((item) => item.id)).toEqual([null, 11])
    expect(payload.items[0].name).toBe('New')
  })

  // REQUIREMENT CHANGED (spec 0427): the section text is replaced by groups + section_index; min/max files by "More documents".
  it('links items to groups by key and sends coherent sections and section_index after a group is moved (AC-007)', () => {
    const values = checklistToFormValues(CHECKLIST)
    expect(values.sections.map((section) => section.name)).toEqual(['Identity', 'Property'])
    expect(values.items[0].section_key).toBe(values.sections[1].key)

    values.sections = [values.sections[1], values.sections[0]]
    const payload = buildChecklistPayload(values)

    expect(payload.sections).toEqual([
      { id: 4, name: 'Property', description: null },
      { id: 3, name: 'Identity', description: 'Personal documents' },
    ])
    expect(payload.items[0].section_index).toBe(0)
  })

  it('maps "More documents" to min 1 and max 1 or 10, and loads max_files 3 checked (AC-008)', () => {
    const values = checklistToFormValues(CHECKLIST)
    expect(values.items[0].allow_multiple).toBe(true)
    expect(buildChecklistPayload(values).items[0]).toMatchObject({ min_files: 1, max_files: 10 })

    values.items[0].allow_multiple = false
    expect(buildChecklistPayload(values).items[0]).toMatchObject({ min_files: 1, max_files: 1 })
  })

  it('ignores the date field when there is no maximum age (AC-009)', () => {
    const values = checklistToFormValues(CHECKLIST)
    values.items[0].date_field = ' valid_until '
    expect(buildChecklistPayload(values).items[0].date_field).toBe('valid_until')

    values.items[0].max_age_days = null
    expect(buildChecklistPayload(values).items[0].date_field).toBeNull()
  })
})
