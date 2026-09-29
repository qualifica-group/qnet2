import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/document-bundles/document-bundle-form-payload'
import type { DocumentBundle } from '@/features/document-bundles/types'
import type { DocumentBundleFormValues } from '@/features/document-bundles/use-document-bundle-form'

const formValues: DocumentBundleFormValues = {
  name: 'Onboarding kit',
  description: 'Standard welcome pack',
  is_active: true,
}

function original(overrides: Partial<DocumentBundle> = {}): DocumentBundle {
  return {
    id: 7,
    name: 'Onboarding kit',
    description: 'Standard welcome pack',
    is_active: true,
    files_count: 2,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0175)', () => {
  it('builds the full create payload shape', () => {
    expect(buildCreatePayload(formValues)).toEqual({
      name: 'Onboarding kit',
      description: 'Standard welcome pack',
      is_active: true,
    })
  })
})

describe('buildUpdatePayload (spec 0175)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Renamed' }, original())).toEqual({
      name: 'Renamed',
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })

  it('never carries files_count (not a form field)', () => {
    const payload = buildUpdatePayload({ ...formValues, name: 'Renamed' }, original())
    expect(payload).not.toHaveProperty('files_count')
  })
})
