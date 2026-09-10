import { describe, expect, it } from 'vitest'
import { buildCreatePayload, buildUpdatePayload } from '@/features/product-categories/product-category-form-payload'
import type { ProductCategoryDetail } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * Manager-labels payload diffing (spec 0080) — split out of
 * `product-category-form-payload.test.ts` once that file reached its size
 * limit (engineering.md §6).
 */

function original(overrides: Partial<ProductCategoryDetail> = {}): ProductCategoryDetail {
  return {
    id: 4,
    name: 'Laptops',
    parent_id: 1,
    parent: { id: 1, name: 'Electronics' },
    inherits_product_attributes: true,
    inherits_quote_attributes: true,
    inherits_work_order_attributes: true,
    description: null,
    attributes: [
      { attribute_id: 9, code: 'ram', name: 'RAM', type: 'integer', is_required: true, sort_order: 0, context: 'quote' },
    ],
    inherited_attributes: [],
    created_at: '2026-01-01T00:00:00Z',
    business_function_id: null,
    requires_quote: false,
    business_function: null,
    effective_business_function: null,
    requires_quote_source_category: null,
    is_selectable: true,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    management_mode_source_category: null,
    single_quote_per_opportunity_source_category: null,
    generates_contract_source_category: null,
    simplified_offer_line_source_category: null,
    manager_labels: {},
    inherits_manager_labels: true,
    inherited_manager_labels: {},
    ...overrides,
  }
}

describe('buildUpdatePayload — manager labels (spec 0080)', () => {
  it('strips blank/whitespace-only rows and trims the rest before sending (AC-042)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      manager_labels: { '1': '  Commercial  ', '2': '', '3': '   ', '4': 'Tutor' },
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      manager_labels: { '1': 'Commercial', '4': 'Tutor' },
    })
  })

  it('does not send manager_labels when the resolved (trimmed) set is unchanged (position-by-position diff)', () => {
    const withOwnLabel = original({ manager_labels: { '2': 'Operator' } })
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      // Different key order / extra blank rows: the position-by-position diff
      // must still see this as unchanged from `withOwnLabel`.
      manager_labels: { '1': '', '2': 'Operator', '3': '', '4': '' },
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, withOwnLabel)).toEqual({})
  })

  it('includes only the changed inherits_manager_labels flag', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      manager_labels: {},
      inherits_manager_labels: false,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ inherits_manager_labels: false })
  })

  it('create payload always sends the trimmed manager_labels and the inheritance flag', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: null,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      manager_labels: { '1': 'Commercial', '2': '  ' },
      inherits_manager_labels: false,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).toMatchObject({
      manager_labels: { '1': 'Commercial' },
      inherits_manager_labels: false,
    })
  })

  // Spec 0080 amendment A1: the cap moved from 4 to 12, positions beyond the
  // old fixed range are ordinary payload keys, no special-casing needed.
  it('sends positions beyond the 4th unchanged, up to the 12-level ceiling (AC-050)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      manager_labels: { '5': 'Regional lead', '12': 'Director' },
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      manager_labels: { '5': 'Regional lead', '12': 'Director' },
    })
    expect(buildCreatePayload(values)).toMatchObject({
      manager_labels: { '5': 'Regional lead', '12': 'Director' },
    })
  })
})
