import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/document-layouts/document-layout-form-payload'
import { createEmptyDocumentLayoutConfig } from '@/features/document-layouts/layout-config-defaults'
import type { DocumentLayoutDetailWithPermissions } from '@/features/document-layouts/types'
import type { DocumentLayoutFormValues } from '@/features/document-layouts/use-document-layout-form'

const formValues: DocumentLayoutFormValues = {
  name: 'Standard quote layout',
  code: 'standard_quote',
  description: 'The default quote layout',
  module: 'quotes',
  is_active: true,
  is_default: false,
}

function original(
  overrides: Partial<DocumentLayoutDetailWithPermissions> = {},
): DocumentLayoutDetailWithPermissions {
  return {
    id: 7,
    name: 'Standard quote layout',
    code: 'standard_quote',
    description: 'The default quote layout',
    module: 'quotes',
    module_label: 'Quotes',
    is_active: true,
    is_default: false,
    config: createEmptyDocumentLayoutConfig(),
    images: [],
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

describe('buildCreatePayload (spec 0069, AC-112)', () => {
  it('builds the full create payload shape, including config', () => {
    const config = createEmptyDocumentLayoutConfig()
    expect(buildCreatePayload(formValues, config)).toEqual({
      name: 'Standard quote layout',
      code: 'standard_quote',
      module: 'quotes',
      config,
      description: 'The default quote layout',
      is_active: true,
      is_default: false,
    })
  })
})

describe('buildUpdatePayload (spec 0069, AC-112)', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(formValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    expect(buildUpdatePayload({ ...formValues, name: 'Renamed layout' }, original())).toEqual({
      name: 'Renamed layout',
    })
  })

  it('includes only the changed description', () => {
    expect(buildUpdatePayload({ ...formValues, description: null }, original())).toEqual({
      description: null,
    })
  })

  it('includes only the changed is_active', () => {
    expect(buildUpdatePayload({ ...formValues, is_active: false }, original())).toEqual({
      is_active: false,
    })
  })

  it('includes only the changed is_default', () => {
    expect(buildUpdatePayload({ ...formValues, is_default: true }, original())).toEqual({
      is_default: true,
    })
  })

  it('NEVER includes code, even when the form value diverges from the original', () => {
    const diverged = { ...formValues, code: 'other_code' }
    expect(buildUpdatePayload(diverged, original())).not.toHaveProperty('code')
  })

  it('NEVER includes module, even when the form value diverges from the original', () => {
    // module only has one real value today, but the payload builder must
    // still refuse to emit the key regardless of form state (spec 0069).
    const diverged = { ...formValues, module: formValues.module }
    expect(buildUpdatePayload(diverged, original())).not.toHaveProperty('module')
  })

  it('omits config when not passed', () => {
    expect(buildUpdatePayload(formValues, original())).not.toHaveProperty('config')
  })

  it('includes config only when it changed from the original', () => {
    const originalRecord = original()
    const unchangedConfig = createEmptyDocumentLayoutConfig()
    expect(buildUpdatePayload(formValues, originalRecord, unchangedConfig)).not.toHaveProperty(
      'config',
    )

    const changedConfig = createEmptyDocumentLayoutConfig()
    changedConfig.page.orientation = 'landscape'
    expect(buildUpdatePayload(formValues, originalRecord, changedConfig)).toEqual({
      config: changedConfig,
    })
  })
})
