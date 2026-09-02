import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/products/product-form-payload'
import type { ProductDetail } from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/**
 * Spec 0017 AC-024: create payload shape, sparse PATCH of changed generic
 * fields. Spec 0061 adds `attribute_values`, additive and scoped to the
 * CURRENT category's product-attribute codes. Spec 0065 AC-079/AC-080 adds
 * the manual `code`: included only when set (trimmed, non-empty) on create,
 * never sent on update (immutable after create), mirroring
 * `project-form-payload.test.ts`.
 */

function original(overrides: Partial<ProductDetail> = {}): ProductDetail {
  return {
    id: 5,
    code: 'PRD-0005',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    category: { id: 3, name: 'Laptops' },
    product_type: 'SERVICE',
    created_at: '2026-01-01T00:00:00Z',
    vat_rate_id: null,
    vat_rate: null,
    supplier_id: null,
    supplier: null,
    unit_of_measure_id: 1,
    unit_of_measure: { id: 1, name: 'Unit', symbol: 'pz' },
    product_typology_id: 1,
    product_typology: { id: 1, name: 'Ente' },
    ...overrides,
  }
}

function values(overrides: Partial<ProductFormValues> = {}): ProductFormValues {
  return {
    code: '',
    name: 'ThinkPad X1',
    description: null,
    cost: 800,
    price: 1200,
    category_id: 3,
    product_type: 'SERVICE',
    vat_rate_id: null,
    supplier_id: null,
    // Match `original()`'s own defaults so the "nothing changed" PATCH
    // assertions below stay accurate; individual tests override them.
    unit_of_measure_id: 1,
    product_typology_id: 1,
    custom_fields: {},
    attribute_values: {},
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the create payload with the generic fields', () => {
    expect(buildCreatePayload(values(), [])).toEqual({
      name: 'ThinkPad X1',
      description: null,
      cost: 800,
      price: 1200,
      category_id: 3,
      product_type: 'SERVICE',
      vat_rate_id: null,
      supplier_id: null,
      unit_of_measure_id: 1,
      // Spec 0099, D-3: sent like `unit_of_measure_id` — a null here makes the
      // server resolve the default typology.
      product_typology_id: 1,
    })
  })

  it('includes the selected VAT rate, supplier and unit of measure ids', () => {
    expect(
      buildCreatePayload(values({ vat_rate_id: 4, supplier_id: 11, unit_of_measure_id: 2 }), []),
    ).toMatchObject({
      vat_rate_id: 4,
      supplier_id: 11,
      unit_of_measure_id: 2,
    })
  })

  it('spec 0088 D-4: includes a null unit_of_measure_id so the server resolves the default unit', () => {
    expect(buildCreatePayload(values({ unit_of_measure_id: null }), [])).toMatchObject({
      unit_of_measure_id: null,
    })
  })

  it('includes valued attribute_values for the current category codes', () => {
    expect(
      buildCreatePayload(values({ attribute_values: { ram_gb: 16, color: null } }), ['ram_gb', 'color']),
    ).toMatchObject({
      attribute_values: { ram_gb: 16 },
    })
  })

  it('omits attribute_values entirely when none of the current codes are valued', () => {
    expect(
      buildCreatePayload(values({ attribute_values: { ram_gb: null } }), ['ram_gb']),
    ).not.toHaveProperty('attribute_values')
  })

  it('ignores a stale code left in form state that no longer belongs to the current category', () => {
    expect(
      buildCreatePayload(values({ attribute_values: { ram_gb: 16, old_code: 'x' } }), ['ram_gb']),
    ).toMatchObject({ attribute_values: { ram_gb: 16 } })
  })

  it('AC-079: includes the trimmed manual code when the user fills it', () => {
    expect(buildCreatePayload(values({ code: '  PRD-0100  ' }), [])).toMatchObject({
      code: 'PRD-0100',
    })
  })

  it('AC-079: omits code when left empty, so the server generates the sequential one', () => {
    expect(buildCreatePayload(values({ code: '' }), [])).not.toHaveProperty('code')
  })
})

describe('buildUpdatePayload', () => {
  it('omits everything when nothing changed', () => {
    expect(buildUpdatePayload(values(), original(), [])).toEqual({})
  })

  it('includes only the changed generic field', () => {
    expect(buildUpdatePayload(values({ name: 'ThinkPad X1 Gen 2' }), original(), [])).toEqual({
      name: 'ThinkPad X1 Gen 2',
    })
  })

  it('treats the decimal STRING the server returns for cost/price as unchanged', () => {
    const serialized = original({ cost: '800.00', price: '1200.00' })

    expect(buildUpdatePayload(values(), serialized, [])).toEqual({})
  })

  it('includes only the changed VAT rate id', () => {
    expect(buildUpdatePayload(values({ vat_rate_id: 4 }), original(), [])).toEqual({
      vat_rate_id: 4,
    })
  })

  it('includes only the changed supplier id', () => {
    expect(buildUpdatePayload(values({ supplier_id: 11 }), original(), [])).toEqual({
      supplier_id: 11,
    })
  })

  it('spec 0088: includes only the changed unit of measure id', () => {
    expect(buildUpdatePayload(values({ unit_of_measure_id: 2 }), original(), [])).toEqual({
      unit_of_measure_id: 2,
    })
  })

  it('includes only the changed attribute_values code, sparse (spec 0061)', () => {
    const withAttributes = original({ attribute_values: { ram_gb: 8, color: 'black' } })
    expect(
      buildUpdatePayload(values({ attribute_values: { ram_gb: 16, color: 'black' } }), withAttributes, [
        'ram_gb',
        'color',
      ]),
    ).toEqual({ attribute_values: { ram_gb: 16 } })
  })

  it('omits attribute_values when nothing among the current codes changed', () => {
    const withAttributes = original({ attribute_values: { ram_gb: 8 } })
    expect(
      buildUpdatePayload(values({ attribute_values: { ram_gb: 8 } }), withAttributes, ['ram_gb']),
    ).toEqual({})
  })

  it('AC-080: never sends code, even when the form value differs from the original (immutable)', () => {
    expect(buildUpdatePayload(values({ code: 'PRD-9999' }), original(), [])).not.toHaveProperty('code')
  })
})
