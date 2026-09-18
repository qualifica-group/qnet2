import { describe, expect, it } from 'vitest'
import { buildUpdatePayload } from '@/features/product-categories/product-category-form-payload'
import type { ProductCategoryDetail } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * `buildUpdatePayload` — the root-only rules (single_quote_per_opportunity,
 * generates_contract, simplified_offer_line) + `report_columns` diffing —
 * split out of `product-category-form-payload.test.ts` once that file
 * reached its size limit (engineering.md §6).
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
    is_reportable: true,
    effective_is_reportable: true,
    is_reportable_source_category: null,
    report_columns: null,
    effective_report_columns: [],
    report_columns_source_category: null,
    inherited_report_columns: [],
    inherited_report_columns_source_category: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: true,
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

describe('buildUpdatePayload — root-only rules', () => {
  // Spec 0080.
  it('never sends single_quote_per_opportunity under a parent, sends it when a root changes it', () => {
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
      is_reportable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: true,
      generates_contract: true,
      simplified_offer_line: true,
      report_columns: null,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    // Under a parent the flag is read-only: the root owns it, so a diff there
    // would be an override attempt the server refuses.
    expect(buildUpdatePayload(values, original())).toEqual({})
    // Already a root: only the flag changed.
    expect(buildUpdatePayload({ ...values, parent_id: null }, original({ parent_id: null, parent: null }))).toEqual({
      single_quote_per_opportunity: true,
    })
  })

  // Spec 0091: identical root-only diffing for the contract rule.
  it('never sends generates_contract under a parent, sends it when a root changes it', () => {
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
      is_reportable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: false,
      simplified_offer_line: true,
      report_columns: null,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
    expect(buildUpdatePayload({ ...values, parent_id: null }, original({ parent_id: null, parent: null }))).toEqual({
      generates_contract: false,
    })
  })

  // Spec 0114: identical root-only diffing for the simplified-offer-line rule.
  it('never sends simplified_offer_line under a parent, sends it when a root changes it', () => {
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
      is_reportable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
      report_columns: null,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    // Under a parent the flag is read-only: the root owns it, so a diff there
    // would be an override attempt the server refuses.
    expect(buildUpdatePayload(values, original())).toEqual({})
    // Already a root: only the flag changed.
    expect(buildUpdatePayload({ ...values, parent_id: null }, original({ parent_id: null, parent: null }))).toEqual({
      simplified_offer_line: false,
    })
  })
})

describe('buildUpdatePayload — report_columns (spec 0141)', () => {
  it('sends the override on its own, order-independent against the original', () => {
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
      is_reportable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      report_columns: ['richiami', 'telefonate'],
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }
    const withOwnColumns = original({ report_columns: ['telefonate', 'richiami'] })

    // Same set, different order: not a change.
    expect(buildUpdatePayload(values, withOwnColumns)).toEqual({})
    // A different set travels.
    expect(buildUpdatePayload({ ...values, report_columns: ['telefonate'] }, withOwnColumns)).toEqual({
      report_columns: ['telefonate'],
    })
    // Back to inheriting: null is a change too.
    expect(buildUpdatePayload({ ...values, report_columns: null }, withOwnColumns)).toEqual({
      report_columns: null,
    })
  })
})
