import { beforeAll, describe, expect, it } from 'vitest'
import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'
import i18n from '@/i18n'
import { buildCreateProductSchema } from '@/features/products/product-schema'
import { buildCustomFieldsSchema } from '@/features/custom-fields/build-custom-fields-schema'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Spec 0065 AC-079: the manual `code` is required (auto-filled by the create
 * form with the next sequential suggestion, editable), trimmed and mirrors
 * the backend's max:32 (mirrors `project-schema.test.ts`'s equivalent block).
 * Only the new `code` field is covered here — the rest of the generic-field
 * schema (name/cost/price/category) is exercised by the form-body suites.
 */

const EMPTY_CUSTOM_FIELDS_SCHEMA = buildCustomFieldsSchema(
  [],
  { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  i18n.t,
)

function baseValues() {
  return {
    code: 'PRD-0001',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    product_type: 'SERVICE' as const,
    usages: ['SALE' as const],
    vat_rate_id: null,
    supplier_id: null,
    unit_of_measure_id: null,
    product_typology_id: null,
    cost_type_id: null,
    custom_fields: {},
    attribute_values: {},
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('buildCreateProductSchema — manual code (spec 0065 AC-079)', () => {
  it('accepts a valid manual code and trims it', () => {
    const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, [])
    const result = schema.safeParse({ ...baseValues(), code: '  PRD-0001  ' })
    expect(result.success).toBe(true)
    if (result.success) {
      expect(result.data.code).toBe('PRD-0001')
    }
  })

  it('rejects an empty code (required, auto-filled by the form)', () => {
    const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, [])
    const result = schema.safeParse({ ...baseValues(), code: '' })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'code')).toBe(true)
    }
  })

  it('rejects a code of 33+ characters (mirrors backend max:32)', () => {
    const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, [])
    const result = schema.safeParse({ ...baseValues(), code: 'A'.repeat(33) })
    expect(result.success).toBe(false)
    if (!result.success) {
      expect(result.error.issues.some((issue) => issue.path.join('.') === 'code')).toBe(true)
    }
  })
})

describe('buildCreateProductSchema — multiselect enum attribute', () => {
  const degree: EffectiveAttribute = {
    id: 30,
    code: 'degree',
    name: 'Titolo di Studio',
    type: 'enum',
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: { display: 'multiselect' },
    relation_target: null,
    is_required: false,
    sort_order: 0,
    inherited: false,
    context: 'product',
    options: [{ value: 'degree', label: 'Laurea', color: null, icon: null, sort_order: 0, is_default: false }],
  }

  it('accepts a list of option codes and rejects a code outside the options', () => {
    const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, [degree])

    expect(schema.safeParse({ ...baseValues(), attribute_values: { degree: ['degree'] } }).success).toBe(true)
    expect(schema.safeParse({ ...baseValues(), attribute_values: { degree: ['phd'] } }).success).toBe(false)
  })
})

describe('buildCreateProductSchema — cost/price required by usage (spec 0191 AC-011)', () => {
  const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, [])

  function errorPaths(overrides: Record<string, unknown>): string[] {
    const result = schema.safeParse({ ...baseValues(), ...overrides })
    return result.success ? [] : result.error.issues.map((issue) => issue.path.join('.'))
  }

  it('accepts a null price and requires the cost for a cost-only product', () => {
    expect(errorPaths({ usages: ['COST'], price: null, cost: 10 })).toEqual([])
    expect(errorPaths({ usages: ['COST'], price: null, cost: null })).toEqual(['cost'])
  })

  it('accepts a null cost and requires the price for a Sellable-only product', () => {
    expect(errorPaths({ usages: ['SALE'], cost: null, price: 10 })).toEqual([])
    expect(errorPaths({ usages: ['SALE'], cost: null, price: null })).toEqual(['price'])
  })

  it('requires both when both usages are on', () => {
    expect(errorPaths({ usages: ['SALE', 'COST'], cost: null, price: null }).sort()).toEqual(['cost', 'price'])
  })
})

// Spec 0288 AC-011: a required attribute in a hidden conditional section does not apply.
function layoutSection(id: string, code: string, visibleWhen: string | null): LayoutSection {
  return {
    id,
    title: id,
    description: null,
    variant: 'default',
    collapsible: false,
    default_collapsed: false,
    columns: 1,
    sort_order: 0,
    rows: [{ id: `${id}-row`, items: [{ attribute_code: code, width: 'full' }] }],
    visible_when: visibleWhen ? { attribute_code: visibleWhen } : null,
  }
}

const CONDITIONAL_LAYOUT: LayoutBlob = {
  sections: [layoutSection('main', 'has_site', null), layoutSection('extra', 'site_access', 'has_site')],
}

function conditionalAttribute(code: string, type: EffectiveAttribute['type'], isRequired: boolean): EffectiveAttribute {
  return {
    id: 1,
    code,
    name: code,
    type,
    description: null,
    help_text: null,
    placeholder: null,
    icon: null,
    config: null,
    relation_target: null,
    is_required: isRequired,
    sort_order: 0,
    inherited: false,
    context: 'product',
    options: [],
  }
}

const CONDITIONAL_ATTRIBUTES = [
  conditionalAttribute('has_site', 'boolean', false),
  conditionalAttribute('site_access', 'text', true),
]

function hasRequiredIssue(result: { success: boolean; error?: { issues: { path: PropertyKey[] }[] } }): boolean {
  return (
    !result.success && (result.error?.issues ?? []).some((issue) => issue.path.join('.') === 'attribute_values.site_access')
  )
}

describe('buildCreateProductSchema - conditional sections (spec 0288 AC-011)', () => {
  it('create: a hidden section skips the required attribute, a visible one enforces it', () => {
    const schema = buildCreateProductSchema(i18n.t, EMPTY_CUSTOM_FIELDS_SCHEMA, CONDITIONAL_ATTRIBUTES, CONDITIONAL_LAYOUT)

    expect(hasRequiredIssue(schema.safeParse({ ...baseValues(), attribute_values: { has_site: false, site_access: null } }))).toBe(false)
    expect(hasRequiredIssue(schema.safeParse({ ...baseValues(), attribute_values: { has_site: true, site_access: null } }))).toBe(true)
  })
})
