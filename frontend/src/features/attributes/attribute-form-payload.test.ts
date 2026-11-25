import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/attributes/attribute-form-payload'
import type { AttributeDetail } from '@/features/attributes/types'
import type { AttributeFormValues } from '@/features/attributes/use-attribute-form'
import { emptyFieldDefinitionValues } from '@/features/custom-fields/field-definition-defaults'

/** Spec 0017/0021: create shape, per-type config/options/relation_target projection, update diffs only changes. */

function values(overrides: Partial<AttributeFormValues> = {}): AttributeFormValues {
  return {
    code: 'weight',
    name: 'Weight',
    ...emptyFieldDefinitionValues(),
    current_attribute_id: null,
    current_attribute_label: null,
    custom_fields: {},
    ...overrides,
  }
}

function original(overrides: Partial<AttributeDetail> = {}): AttributeDetail {
  return {
    id: 7,
    code: 'color',
    name: 'Color',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    current_attribute_id: null,
    options: [
      { id: 1, value: 'red', label: 'Red', color: null, icon: null, sort_order: 0, is_default: false },
      { id: 2, value: 'blue', label: 'Blue', color: null, icon: null, sort_order: 1, is_default: false },
    ],
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('omits options/config/relation_target for a plain text attribute', () => {
    expect(buildCreatePayload(values({ type: 'text' }))).toEqual({
      code: 'weight',
      name: 'Weight',
      type: 'text',
      description: undefined,
      help_text: undefined,
      placeholder: undefined,
      icon: undefined,
      config: undefined,
      relation_target: undefined,
      options: undefined,
    })
  })

  it('includes options with a positional sort_order for an enum attribute', () => {
    const payload = buildCreatePayload(
      values({
        type: 'enum',
        options: [
          { value: 'red', label: 'Red', color: '', icon: '', is_default: false },
          { value: 'blue', label: 'Blue', color: 'blue', icon: 'circle', is_default: true },
        ],
      }),
    )

    expect(payload.options).toEqual([
      { value: 'red', label: 'Red', color: undefined, icon: undefined, sort_order: 0, is_default: false },
      { value: 'blue', label: 'Blue', color: 'blue', icon: 'circle', sort_order: 1, is_default: true },
    ])
  })

  it('projects the per-type config for a decimal attribute', () => {
    const payload = buildCreatePayload(
      values({
        type: 'decimal',
        config: { ...emptyFieldDefinitionValues().config, min: 0, decimals: 2 },
      }),
    )

    expect(payload.config).toEqual({ min: 0, decimals: 2 })
    expect(payload.options).toBeUndefined()
  })

  it('includes the relation_target for a relation attribute', () => {
    const payload = buildCreatePayload(
      values({
        type: 'relation',
        relation_target: { entity_type: 'products', cardinality: 'many', for_select_resource: 'products' },
      }),
    )

    expect(payload.relation_target).toEqual({
      entity_type: 'products',
      cardinality: 'many',
      for_select_resource: 'products',
    })
    expect(payload.options).toBeUndefined()
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const enumValues = values({
      code: 'color',
      name: 'Color',
      type: 'enum',
      options: [
        { value: 'red', label: 'Red', color: '', icon: '', is_default: false },
        { value: 'blue', label: 'Blue', color: '', icon: '', is_default: false },
      ],
    })

    expect(buildUpdatePayload(enumValues, original())).toEqual({})
  })

  it('includes only the changed name', () => {
    const enumValues = values({
      code: 'color',
      name: 'Colour',
      type: 'enum',
      options: [
        { value: 'red', label: 'Red', color: '', icon: '', is_default: false },
        { value: 'blue', label: 'Blue', color: '', icon: '', is_default: false },
      ],
    })

    expect(buildUpdatePayload(enumValues, original())).toEqual({ name: 'Colour' })
  })

  it('sends a full option replacement when the option set changed', () => {
    const enumValues = values({
      code: 'color',
      name: 'Color',
      type: 'enum',
      options: [{ value: 'red', label: 'Red', color: '', icon: '', is_default: false }],
    })

    expect(buildUpdatePayload(enumValues, original())).toEqual({
      options: [{ value: 'red', label: 'Red', color: undefined, icon: undefined, sort_order: 0, is_default: false }],
    })
  })
})
