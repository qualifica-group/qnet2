import { describe, expect, it } from 'vitest'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttribute } from '@/features/request-management/types'

/** Spec 0062: `ApplicableAttribute` -> `EffectiveAttribute`, feeding `AttributeLayoutRenderer`. */

function attribute(overrides: Partial<ApplicableAttribute> = {}): ApplicableAttribute {
  return {
    id: 7,
    code: 'priority',
    name: 'Priority',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: true,
    sort_order: 3,
    options: [{ value: 'low', label: 'Low', color: null }],
    ...overrides,
  }
}

describe('toEffectiveAttribute', () => {
  it('maps every scalar field 1:1, defaulting inherited to false', () => {
    const result = toEffectiveAttribute(attribute())

    expect(result).toMatchObject({
      id: 7,
      code: 'priority',
      name: 'Priority',
      type: 'enum',
      is_required: true,
      sort_order: 3,
      inherited: false,
      context: 'opportunity',
    })
    expect(result.options).toEqual([
      { value: 'low', label: 'Low', color: null, icon: null, sort_order: 0, is_default: false },
    ])
  })

  it('accepts an explicit context override (products detail, spec 0062)', () => {
    const result = toEffectiveAttribute(attribute(), 'product')
    expect(result.context).toBe('product')
  })

  it('resolves a well-formed relation_target', () => {
    const result = toEffectiveAttribute(
      attribute({ relation_target: { for_select_resource: 'registries', cardinality: 'many' } }),
    )
    expect(result.relation_target).toEqual({
      entity_type: '',
      for_select_resource: 'registries',
      cardinality: 'many',
    })
  })

  it('drops a malformed relation_target instead of crashing', () => {
    const result = toEffectiveAttribute(attribute({ relation_target: { cardinality: 'one' } }))
    expect(result.relation_target).toBeNull()
  })
})
