import type { UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { DEFAULT_PRODUCT_USAGES } from '@/features/products/product-form-payload'
import type { SupplierCommission } from '@/features/products/product-suppliers'
import type { ProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type {
  ProductBusinessFunctionSummary,
  ProductCategorySummary,
  ProductCostTypeSummary,
  ProductDetail,
  ProductType,
  ProductTypologySummary,
  ProductUsage,
} from '@/features/products/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** One supplier line the record shows: the persisted option, or the draft row once its supplier is picked. */
export interface ProductRecordSupplier {
  supplier_id: number
  name: string
  price: string | number | null
  cost: string | number | null
  is_default: boolean
  /** Read-only detail only (rev.3 D-22): the Fornitore commission, null when none. */
  commission?: SupplierCommission | null
}

const NO_SUPPLIERS: ProductRecordSupplier[] = []

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
  /** Default cost type (spec 0251); the draft carries the colour once the picker has resolved it. */
  cost_type: ProductCostTypeSummary | null
  usages: ProductUsage[]
  vat_rate: RelationFieldRef | null
  /** Spec 0320: default first; on a draft only the rows whose supplier is already picked. */
  suppliers: ProductRecordSupplier[]
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
  /** The saved product (detail); null on create, where supplier commissions are looked up by category. */
  productId: number | null
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
    cost_type: product.cost_type ?? null,
    usages: product.usages ?? DEFAULT_PRODUCT_USAGES,
    vat_rate: product.vat_rate,
    suppliers: product.suppliers ?? NO_SUPPLIERS,
    custom_fields: product.custom_fields ?? {},
    attribute_values: product.attribute_values ?? {},
  }
}
