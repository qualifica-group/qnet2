import { describe, expect, it } from 'vitest'
import { buildCreatePayload } from '@/features/product-categories/product-category-form-payload'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * `buildCreatePayload` alone — split out of
 * `product-category-form-payload.test.ts` (which keeps the `buildUpdatePayload`
 * diffing suite) once that file reached its size limit (engineering.md §6).
 */
describe('buildCreatePayload', () => {
  it('builds the full create payload shape', () => {
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
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).toEqual({
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [{ attribute_id: 9, context: 'quote', is_required: true, sort_order: 0 }],
      business_function_id: null,
      is_selectable: true,
      manager_labels: {},
      inherits_manager_labels: true,
    })
  })

  it('omits requires_quote under a parent (inherited) and sends it at the root (owned)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
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
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).not.toHaveProperty('requires_quote')
    expect(buildCreatePayload({ ...values, parent_id: null })).toMatchObject({ requires_quote: true })
  })

  it('omits management_mode under a parent (inherited) and sends it at the root (owned)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'single',
      single_quote_per_opportunity: false,
      generates_contract: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).not.toHaveProperty('management_mode')
    expect(buildCreatePayload({ ...values, parent_id: null })).toMatchObject({ management_mode: 'single' })
  })

  it('omits single_quote_per_opportunity under a parent (inherited) and sends it at the root (owned)', () => {
    const values: ProductCategoryFormValues = {
      name: 'Laptops',
      parent_id: 1,
      inherits_product_attributes: true,
      inherits_quote_attributes: true,
      inherits_work_order_attributes: true,
      description: null,
      attributes: [],
      business_function_id: null,
      requires_quote: false,
      is_selectable: true,
      management_mode: 'multiple',
      single_quote_per_opportunity: true,
      generates_contract: true,
      manager_labels: {},
      inherits_manager_labels: true,
      custom_fields: {},
    }

    expect(buildCreatePayload(values)).not.toHaveProperty('single_quote_per_opportunity')
    expect(buildCreatePayload({ ...values, parent_id: null })).toMatchObject({
      single_quote_per_opportunity: true,
      generates_contract: true,
    })
  })
})
