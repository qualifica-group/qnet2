import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildDocumentChecklistCategorySchema } from '@/features/document-checklist-categories/document-checklist-category-schema'
import {
  buildCategoryPayload,
  buildDefaultCategoryValues,
  categoryToFormValues,
} from '@/features/document-checklist-categories/document-checklist-category-form-values'

/** Spec 0410 AC-018: the category form schema mirrors the backend rules. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const schema = () => buildDocumentChecklistCategorySchema(i18n.t)

function valid(overrides: Record<string, unknown> = {}) {
  return { name: 'Mortgages', description: '', sort_order: 0, ...overrides }
}

function errorPaths(values: unknown): string[] {
  const result = schema().safeParse(values)
  return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
}

describe('buildDocumentChecklistCategorySchema (spec 0410)', () => {
  it('accepts a valid category and the defaults', () => {
    expect(schema().safeParse(valid()).success).toBe(true)
    expect(schema().safeParse(buildDefaultCategoryValues()).success).toBe(false)
    expect(buildDefaultCategoryValues().sort_order).toBe(0)
  })

  it('requires the name and caps it at 255 characters', () => {
    expect(errorPaths(valid({ name: '' }))).toContain('name')
    expect(errorPaths(valid({ name: 'a'.repeat(256) }))).toContain('name')
    expect(errorPaths(valid({ name: 'a'.repeat(255) }))).toEqual([])
  })

  it('caps the description at 2000 characters', () => {
    expect(errorPaths(valid({ description: 'a'.repeat(2001) }))).toContain('description')
    expect(errorPaths(valid({ description: 'a'.repeat(2000) }))).toEqual([])
  })

  it('accepts sort_order only as an integer within 0..65535', () => {
    expect(errorPaths(valid({ sort_order: -1 }))).toContain('sort_order')
    expect(errorPaths(valid({ sort_order: 65536 }))).toContain('sort_order')
    expect(errorPaths(valid({ sort_order: 1.5 }))).toContain('sort_order')
    expect(errorPaths(valid({ sort_order: Number.NaN }))).toContain('sort_order')
    expect(errorPaths(valid({ sort_order: 65535 }))).toEqual([])
  })

  it('maps a saved category to the form and back, empty description as null', () => {
    const saved = {
      id: 3,
      name: ' Mortgages ',
      description: null,
      sort_order: 5,
      checklists_count: 2,
      sync_status: 'synced' as const,
      sync_error: null,
      synced_at: null,
      created_at: '2026-11-23T10:00:00Z',
      updated_at: '2026-11-23T10:00:00Z',
    }

    expect(categoryToFormValues(saved)).toEqual({ name: ' Mortgages ', description: '', sort_order: 5 })
    expect(buildCategoryPayload(categoryToFormValues(saved))).toEqual({ name: 'Mortgages', description: null, sort_order: 5 })
  })
})
