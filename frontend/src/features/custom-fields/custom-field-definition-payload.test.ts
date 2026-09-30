import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/custom-fields/custom-field-definition-payload'
import { emptyTableDefinition } from '@/features/custom-fields/field-definition-table'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/** Spec 0021 AC-025/AC-018: create shape, per-type config/options/relation_target projection, update diffs only changes. */

function emptyConfig(): CustomFieldDefinitionFormValues['config'] {
  return {
    minLength: null,
    maxLength: null,
    regex: '',
    transform: '',
    rows: null,
    min: null,
    max: null,
    step: null,
    decimals: null,
    display: '',
  }
}

function emptyValidation(): CustomFieldDefinitionFormValues['validation'] {
  return {
    required: false,
    unique: false,
    min: null,
    max: null,
    regex: '',
    email: false,
    url: false,
    exists: false,
    distinct: false,
  }
}

function baseValues(
  overrides: Partial<CustomFieldDefinitionFormValues> = {},
): CustomFieldDefinitionFormValues {
  return {
    entity_type: 'companies',
    key: 'loyalty_tier',
    type: 'text',
    label: 'Loyalty tier',
    description: '',
    help_text: '',
    placeholder: '',
    icon: '',
    group: '',
    tab: '',
    sort_order: 0,
    is_indexed: false,
    is_active: true,
    config: emptyConfig(),
    validation: emptyValidation(),
    relation_target: { entity_type: '', cardinality: 'one', for_select_resource: '' },
    options: [],
    table: emptyTableDefinition(),
    ...overrides,
  }
}

function original(overrides: Partial<CustomFieldDefinitionDetail> = {}): CustomFieldDefinitionDetail {
  return {
    id: 9,
    entity_type: 'companies',
    key: 'loyalty_tier',
    type: 'text',
    label: 'Loyalty tier',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    group: null,
    tab: null,
    sort_order: 0,
    default_value: null,
    config: null,
    validation: null,
    relation_target: null,
    is_indexed: false,
    is_active: true,
    options: [],
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds a minimal text field payload with undefined per-type projections', () => {
    const payload = buildCreatePayload(baseValues())

    expect(payload).toEqual({
      entity_type: 'companies',
      key: 'loyalty_tier',
      type: 'text',
      label: 'Loyalty tier',
      description: undefined,
      help_text: undefined,
      placeholder: undefined,
      icon: undefined,
      group: undefined,
      tab: undefined,
      sort_order: 0,
      config: undefined,
      validation: undefined,
      relation_target: undefined,
      is_indexed: false,
      is_active: true,
      options: undefined,
    })
  })

  it('projects the text config subset (minLength/maxLength/regex/transform)', () => {
    const values = baseValues({
      config: { ...emptyConfig(), minLength: 2, maxLength: 50, regex: '^[a-z]+$', transform: 'upper' },
    })

    expect(buildCreatePayload(values).config).toEqual({
      minLength: 2,
      maxLength: 50,
      regex: '^[a-z]+$',
      transform: 'upper',
    })
  })

  it('includes options with a positional sort_order for an enum field, omitting relation_target', () => {
    const values = baseValues({
      type: 'enum',
      config: { ...emptyConfig(), display: 'select' },
      options: [
        { value: 'gold', label: 'Gold', color: 'amber', icon: '', is_default: true },
        { value: 'silver', label: 'Silver', color: '', icon: '', is_default: false },
      ],
    })

    const payload = buildCreatePayload(values)

    expect(payload.options).toEqual([
      { value: 'gold', label: 'Gold', color: 'amber', icon: undefined, sort_order: 0, is_default: true },
      { value: 'silver', label: 'Silver', color: undefined, icon: undefined, sort_order: 1, is_default: false },
    ])
    expect(payload.config).toEqual({ display: 'select' })
    expect(payload.relation_target).toBeUndefined()
  })

  it('includes relation_target for a relation field, omitting options', () => {
    const values = baseValues({
      type: 'relation',
      relation_target: { entity_type: 'products', cardinality: 'many', for_select_resource: 'products' },
    })

    const payload = buildCreatePayload(values)

    expect(payload.relation_target).toEqual({
      entity_type: 'products',
      cardinality: 'many',
      for_select_resource: 'products',
    })
    expect(payload.options).toBeUndefined()
    expect(payload.config).toBeUndefined()
  })

  it('projects the validation builder rules', () => {
    const values = baseValues({
      validation: { ...emptyValidation(), required: true, min: 1, max: 10 },
    })

    expect(buildCreatePayload(values).validation).toEqual({ required: true, min: 1, max: 10 })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    expect(buildUpdatePayload(baseValues(), original())).toEqual({})
  })

  it('includes only the changed label', () => {
    const values = baseValues({ label: 'VIP tier' })

    expect(buildUpdatePayload(values, original())).toEqual({ label: 'VIP tier' })
  })

  it('sends a full option replacement when the option set changed on an enum field', () => {
    const enumOriginal = original({
      type: 'enum',
      options: [{ id: 1, value: 'gold', label: 'Gold', color: null, icon: null, sort_order: 0, is_default: false }],
    })
    const values = baseValues({
      type: 'enum',
      options: [{ value: 'gold', label: 'Gold Tier', color: '', icon: '', is_default: false }],
    })

    expect(buildUpdatePayload(values, enumOriginal)).toEqual({
      options: [{ value: 'gold', label: 'Gold Tier', color: undefined, icon: undefined, sort_order: 0, is_default: false }],
    })
  })

  it('includes relation_target only when it changed', () => {
    const relationOriginal = original({
      type: 'relation',
      relation_target: { entity_type: 'products', cardinality: 'one', for_select_resource: 'products' },
    })
    const values = baseValues({
      type: 'relation',
      relation_target: { entity_type: 'products', cardinality: 'many', for_select_resource: 'products' },
    })

    expect(buildUpdatePayload(values, relationOriginal)).toEqual({
      relation_target: { entity_type: 'products', cardinality: 'many', for_select_resource: 'products' },
    })
  })
})
