import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { buildAttributeValuesSchema } from '@/features/request-management/attribute-values-schema'
import type { ApplicableAttribute } from '@/features/request-management/types'

/**
 * A multiselect `enum` attribute (e.g. "Titolo di Studio") holds a LIST of
 * option codes, mirroring the backend's `array` + per-element rule: validating
 * it as a single string rejected every pick with Zod's "Invalid input".
 */

function enumAttribute(display: 'select' | 'multiselect'): ApplicableAttribute {
  return {
    id: 30,
    code: 'degree',
    name: 'Titolo di Studio',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: { display },
    relation_target: null,
    is_required: false,
    sort_order: 0,
    options: [
      { value: 'high_school', label: 'Diploma', color: null },
      { value: 'degree', label: 'Laurea', color: null },
    ],
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildAttributeValuesSchema — multiselect enum', () => {
  const schema = buildAttributeValuesSchema([enumAttribute('multiselect')], i18n.t)

  it('accepts a list of valid option codes', () => {
    expect(schema.safeParse({ degree: ['high_school', 'degree'] }).success).toBe(true)
  })

  it('accepts an unset (null) and an emptied ([]) selection', () => {
    expect(schema.safeParse({ degree: null }).success).toBe(true)
    expect(schema.safeParse({ degree: [] }).success).toBe(true)
  })

  it('rejects a code outside the options, on that element', () => {
    const result = schema.safeParse({ degree: ['degree', 'phd'] })

    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.map((issue) => issue.path.join('.'))).toEqual(['degree.1'])
    }
  })
})

describe('buildAttributeValuesSchema — single enum', () => {
  const schema = buildAttributeValuesSchema([enumAttribute('select')], i18n.t)

  it('keeps validating a single pick as one option code', () => {
    expect(schema.safeParse({ degree: 'degree' }).success).toBe(true)
    expect(schema.safeParse({ degree: 'phd' }).success).toBe(false)
    expect(schema.safeParse({ degree: ['degree'] }).success).toBe(false)
  })
})
