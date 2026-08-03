import { describe, expect, it } from 'vitest'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/product-categories/product-category-form-payload'
import type { ProductCategoryDetail } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Spec 0017 AC-010: create shape, update diffs generic fields + full-replace attributes sync (spec 0061 adds `context`). */

function original(overrides: Partial<ProductCategoryDetail> = {}): ProductCategoryDetail {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    inherits_product_attributes: true,
    inherits_opportunity_attributes: true,
    description: null,
    attributes: [
      { attribute_id: 9, code: 'ram', name: 'RAM', type: 'integer', is_required: true, sort_order: 0, context: 'opportunity' },
    ],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    requires_quote: false,
    business_function: null,
    effective_business_function: null,
    requires_quote_source_category: null,
    is_selectable: true,
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      is_selectable: true,
    })
  })

  it('omits requires_quote under a parent (inherited) and sends it at the root (owned)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).not.toHaveProperty('requires_quote')
    expect(buildCreatePayload({ ...values, parent_id: null })).toMatchObject({ requires_quote: true })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed parent_id', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 2,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: 2 })
  })

  it('includes only the inheritance flag of the context that changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: false,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ inherits_product_attributes: false })
    expect(
      buildUpdatePayload({ ...values, inherits_product_attributes: true, inherits_opportunity_attributes: false }, original()),
    ).toEqual({ inherits_opportunity_attributes: false })
  })

  it('sends a full attributes replacement when the assignment set changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: false, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: false, sort_order: 0 }],
    })
  })

  it('sends a full attributes replacement when only the context changed (same attribute, product instead of opportunity)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'product', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      attributes: [{ attribute_id: 9, context: 'product', is_required: true, sort_order: 0 }],
    })
  })

  it('includes the changed business_function_id when the category does not inherit one', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: 5,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ business_function_id: 5 })
  })

  it('omits business_function_id when it stays null while the category inherits one (spec 0023 AC-015)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      custom_fields: {},
    }
    const inheriting = original({
      effective_business_function: { id: 1, name: 'Sales', inherited: true, source_category: { id: 1, name: 'Electronics' } },
    })

    expect(buildUpdatePayload(values, inheriting)).toEqual({})
  })

  it('never sends requires_quote while the category sits under a parent (the root owns it)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('sends is_selectable on its own, whatever the parent (spec 0074 D-2)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: false,
      custom_fields: {},
    }

    // Under a parent — where requires_quote would be withheld — the flag still travels.
    expect(buildUpdatePayload(values, original())).toEqual({ is_selectable: false })
  })

  it('sends requires_quote when a root category changes it, and on promotion to root', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: null,
      inherits_product_attributes: true,
      inherits_opportunity_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      custom_fields: {},
    }

    // Already a root: only the flag changed.
    expect(buildUpdatePayload(values, original({ parent_id: null, parent: null }))).toEqual({
      requires_quote: true,
    })
    // Promoted to root in the same save: both travel, the server accepts it.
    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: null, requires_quote: true })
  })
})
