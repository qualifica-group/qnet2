import type { UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { DEFAULT_PRODUCT_USAGES } from '@/features/products/product-form-payload'
import type { ProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type {
  ProductBusinessFunctionSummary,
  ProductCategorySummary,
  ProductDetail,
  ProductType,
  ProductTypologySummary,
  ProductUsage,
} from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** What the record's rows show: the persisted product on the detail, the draft (with resolved labels) on create. */
export interface ProductRecordValues {
  code: string
  name: string
  description: string | null
  cost: string | number | null
  price: string | number | null
  category: ProductCategorySummary | null
  /** Read-only, derived from the category server-side (spec 0023); `null` on a create draft. */
  business_function: ProductBusinessFunctionSummary | null
  product_typology: ProductTypologySummary | RelationFieldRef | null
  unit_of_measure: RelationFieldRef | null
  product_type: ProductType
  usages: ProductUsage[]
  vat_rate: RelationFieldRef | null
  supplier: RelationFieldRef | null
  custom_fields: Record<string, CustomFieldValue>
  attribute_values: Record<string, CustomFieldValue>
}

/**
 * Everything a record section edits with: the shown values, the form, the
 * single open row, the category picker and — on the detail — what the open
 * editor's cascade brings in (spec 0218 D-4).
 */
export interface ProductRecordSectionProps {
  values: ProductRecordValues
  form: UseFormReturn<ProductFormValues>
  inline: InlineEdit
  categories: ProductCategoryPicker
  onCategoryChange: (categoryId: number) => void
  /** Detail only: the attributes a category change adds, filled in the category editor; empty on create. */
  newAttributes: EffectiveAttribute[]
  /** Detail only: the usages the persisted product already has, so a newly-enabled one asks its amount. */
  persistedUsages: ProductUsage[] | null
}

/** The persisted product as the record's rows read it. */
export function persistedProductValues(product: ProductDetail): ProductRecordValues {
  return {
    code: product.code,
    name: product.name,
    description: product.description,
    cost: product.cost,
    price: product.price,
    category: product.category,
    business_function: product.business_function ?? null,
    product_typology: product.product_typology,
    unit_of_measure: product.unit_of_measure,
    product_type: product.product_type,
    usages: product.usages ?? DEFAULT_PRODUCT_USAGES,
    vat_rate: product.vat_rate,
    supplier: product.supplier,
    custom_fields: product.custom_fields ?? {},
    attribute_values: product.attribute_values ?? {},
  }
}
