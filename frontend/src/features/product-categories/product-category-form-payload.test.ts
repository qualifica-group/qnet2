import { describe, expect, it } from 'vitest'
import { buildUpdatePayload } from '@/features/product-categories/product-category-form-payload'
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

describe('buildUpdatePayload', () => {
  it('omits every field when nothing changed', () => {
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
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('includes only the changed parent_id', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 2,
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
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: 2 })
  })

  it('includes only the inheritance flag of the context that changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: false,
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
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ inherits_product_attributes: false })
    expect(
      buildUpdatePayload({ ...values, inherits_product_attributes: true, inherits_quote_attributes: false }, original()),
    ).toEqual({ inherits_quote_attributes: false })
    // Spec 0098: the Commessa barrier is a third, independent flag.
    expect(
      buildUpdatePayload(
        { ...values, inherits_product_attributes: true, inherits_work_order_attributes: false },
        original(),
      ),
    ).toEqual({ inherits_work_order_attributes: false })
  })

  it('sends a full attributes replacement when the assignment set changed', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: false, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({
      attributes: [{ attribute_id: 9, context: 'quote', is_required: false, sort_order: 0 }],
    })
  })

  it('sends a full attributes replacement when only the context changed (same attribute, quote instead of product)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'product', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
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
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: 5,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({ business_function_id: 5 })
  })

  it('omits business_function_id when it stays null while the category inherits one (spec 0023 AC-015)', () => {
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
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
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
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('sends is_selectable on its own, whatever the parent (spec 0074 D-2)', () => {
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
      is_selectable: false,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
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
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: true,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    // Already a root: only the flag changed.
    expect(buildUpdatePayload(values, original({ parent_id: null, parent: null }))).toEqual({
      requires_quote: true,
    })
    // Promoted to root in the same save: both travel, the server accepts it.
    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: null, requires_quote: true })
  })

  it('never sends management_mode while the category sits under a parent (the root owns it)', () => {
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
      management_mode: 'single',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildUpdatePayload(values, original())).toEqual({})
  })

  it('sends management_mode when a root category changes it, and on promotion to root', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: null,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'single',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    // Already a root: only the mode changed.
    expect(buildUpdatePayload(values, original({ parent_id: null, parent: null }))).toEqual({
      management_mode: 'single',
    })
    // Promoted to root in the same save: both travel, the server accepts it.
    expect(buildUpdatePayload(values, original())).toEqual({ parent_id: null, management_mode: 'single' })
  })

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
      management_mode: 'multiple',
      single_quote_per_opportunity: true,
      generates_contract: true,
      simplified_offer_line: true,
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
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: false,
      simplified_offer_line: true,
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
      management_mode: 'multiple',
      single_quote_per_opportunity: false,
      generates_contract: true,
      simplified_offer_line: false,
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
