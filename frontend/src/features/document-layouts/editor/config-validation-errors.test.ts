import { describe, expect, it } from 'vitest'
import { parseConfigValidationErrors } from '@/features/document-layouts/editor/config-validation-errors'

/** Spec 0069 AC-129: a `config.*` 422 path is attributed to the exact block, not a generic message. */
describe('parseConfigValidationErrors (AC-129)', () => {
  it('maps a nested block path onto its zone and block index', () => {
    const errors = parseConfigValidationErrors({
      'config.body.blocks.3.runs.0.size': ['Size must be between 6 and 72.'],
    })

    expect(errors).toEqual([
      {
        zone: 'body',
        blockIndex: 3,
        path: ['runs', '0', 'size'],
        rawPath: 'config.body.blocks.3.runs.0.size',
        message: 'Size must be between 6 and 72.',
      },
    ])
  })

  it('maps a page-level path with no zone/block index', () => {
    const errors = parseConfigValidationErrors({
      'config.page.margins.top': ['Margin must be at most 5670.'],
    })

    expect(errors).toEqual([
      {
        zone: null,
        blockIndex: null,
        path: ['page', 'margins', 'top'],
        rawPath: 'config.page.margins.top',
        message: 'Margin must be at most 5670.',
      },
    ])
  })

  it('ignores errors on keys unrelated to config', () => {
    const errors = parseConfigValidationErrors({ name: ['Name already taken.'] })
    expect(errors).toEqual([])
  })

  it('returns an empty array when there are no errors', () => {
    expect(parseConfigValidationErrors(undefined)).toEqual([])
  })
})
