import type { UseFormReturn } from 'react-hook-form'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import type { ProductCategoryRecordValues } from '@/features/product-categories/product-category-record'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Everything a record section edits with: the shown values, the form, the single open row and the editors' mode. */
export interface ProductCategoryRecordSectionProps {
  values: ProductCategoryRecordValues
  form: UseFormReturn<ProductCategoryFormValues>
  inline: InlineEdit
  mode: ProductCategoryFormMode
  /** `false` without the category's `update` permission: no row offers a pencil. */
  canEdit: boolean
}
