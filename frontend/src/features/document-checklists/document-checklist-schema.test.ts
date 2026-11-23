import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildDocumentChecklistSchema } from '@/features/document-checklists/document-checklist-schema'
import { buildDefaultItem } from '@/features/document-checklists/document-checklist-form-values'

/** Spec 0403 AC-023: the form schema mirrors the backend rules. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const schema = () => buildDocumentChecklistSchema(i18n.t)

function valid(overrides: Record<string, unknown> = {}) {
  return { name: 'Opening', code: 'OPEN_1', description: '', category_id: null, is_active: true, items: [buildDefaultItem()], ...overrides }
}

function validWithItem(overrides: Record<string, unknown>) {
  return valid({ items: [{ ...buildDefaultItem(), name: 'ID card', ...overrides }] })
}

function errorPaths(values: unknown): string[] {
  const result = schema().safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('buildDocumentChecklistSchema (spec 0403)', () => {
  it('accepts a valid checklist', () => {
    expect(schema().safeParse(validWithItem({})).success).toBe(true)
  })

  it('accepts a checklist with no items', () => {
    expect(schema().safeParse(valid({ items: [] })).success).toBe(true)
  })

  it('rejects a code with characters outside the allowed set', () => {
    expect(errorPaths(valid({ code: 'ab c' }))).toContain('code')
  })

  it('rejects a code longer than 50 characters and a name longer than 255', () => {
    expect(errorPaths(valid({ code: 'A'.repeat(51) }))).toContain('code')
    expect(errorPaths(valid({ name: 'N'.repeat(256) }))).toContain('name')
  })

  it('rejects max_files lower than min_files on max_files', () => {
    expect(errorPaths(validWithItem({ min_files: 3, max_files: 2 }))).toContain('items.0.max_files')
  })

  it('rejects an item without file types and duplicated file types', () => {
    expect(errorPaths(validWithItem({ allowed_file_types: [] }))).toContain('items.0.allowed_file_types')
    expect(errorPaths(validWithItem({ allowed_file_types: ['pdf', 'pdf'] }))).toContain('items.0.allowed_file_types')
  })

  it('rejects an unknown file type', () => {
    expect(errorPaths(validWithItem({ allowed_file_types: ['exe'] })).length).toBeGreaterThan(0)
  })

  it('rejects a file size above 20480 KB and accepts the limit', () => {
    expect(errorPaths(validWithItem({ max_file_size_kb: 20481 }))).toContain('items.0.max_file_size_kb')
    expect(schema().safeParse(validWithItem({ max_file_size_kb: 20480 })).success).toBe(true)
  })

  it('rejects max_age_days out of 1..3650 and accepts null', () => {
    expect(errorPaths(validWithItem({ max_age_days: 3651 }))).toContain('items.0.max_age_days')
    expect(errorPaths(validWithItem({ max_age_days: 0 }))).toContain('items.0.max_age_days')
    expect(schema().safeParse(validWithItem({ max_age_days: null })).success).toBe(true)
  })

  it('rejects more than 200 items', () => {
    const items = Array.from({ length: 201 }, () => ({ ...buildDefaultItem(), name: 'Doc' }))
    expect(errorPaths(valid({ items }))).toContain('items')
  })

  it('rejects an item without a name', () => {
    expect(errorPaths(valid())).toContain('items.0.name')
  })
})
