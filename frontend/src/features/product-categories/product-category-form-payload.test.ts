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
    inherits_attributes: true,
    description: null,
    attributes: [
      { attribute_id: 9, code: 'ram', name: 'RAM', type: 'integer', is_required: true, sort_order: 0, context: 'opportunity' },
    ],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    business_function: null,
    effective_business_function: null,
    ...overrides,
  }
}

describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
    })
  })
})

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed parent_id', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 2,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: 2 })
  })

  it('includes only the changed inherits_attributes', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: false,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ inherits_attributes: false })
  })

  it('sends a full attributes replacement when the assignment set changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: false, sort_order: 0 }],
      business_function_id: null,
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
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'product', is_required: true, sort_order: 0 }],
      business_function_id: null,
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
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: 5,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ business_function_id: 5 })
  })

  it('omits business_function_id when it stays null while the category inherits one (spec 0023 AC-015)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'opportunity', is_required: true, sort_order: 0 }],
      business_function_id: null,
      custom_fields: {},
    }
    const inheriting = original({
      effective_business_function: { id: 1, name: 'Sales', inherited: true, source_category: { id: 1, name: 'Electronics' } },
    })

    expect(buildUpdatePayload(values, inheriting)).toEqual({})
  })
})
