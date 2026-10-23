import { useMemo } from 'react'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { useAbilities } from '@/features/auth/use-abilities'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttribute } from '@/features/request-management/types'
import { ProductAttributesSection } from '@/features/products/product-attributes-section'
import { persistedProductValues } from '@/features/products/product-record'
import {
  ProductClassificationRecordSection,
  ProductIdentityRecordSection,
  ProductPricingRecordSection,
} from '@/features/products/product-record-sections'
import { StockProductSection } from '@/features/stock/stock-product-section'
import { STOCK_VIEW_PERMISSION } from '@/features/stock/types'
import { useProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type { ProductDetailEditor } from '@/features/products/use-product-inline-edit'
import type { ProductDetailWithPermissions } from '@/features/products/types'

/** Stable empty default: a payload without attributes reads the same as `[]`. */
const NO_ATTRIBUTES: ApplicableAttribute[] = []

interface ProductDetailSectionsProps {
  product: ProductDetailWithPermissions
  editor: ProductDetailEditor
}

/**
 * The product record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0218): identity, classification, pricing and supply,
 * the category's attributes, then the custom fields ("campi flessibili")
 * grouped as the form groups them. The create form renders the same sections
 * in the same order (`ProductCreateSections`).
 */
export function ProductDetailSections({ product, editor }: ProductDetailSectionsProps) {
  const { state, inline } = editor
  const { form } = state
  const { can } = useAbilities()
  const categories = useProductCategoryPicker(product.category_id)
  const values = persistedProductValues(product)
  const attributes = product.applicable_attributes ?? NO_ATTRIBUTES
  const editable = useMemo(
    () => new Map(attributes.map((attribute) => [attribute.code, toEffectiveAttribute(attribute, 'product')])),
    [attributes],
  )
  // D-4: what a category change in the open editor adds to the persisted set.
  const newAttributes = state.productAttributes.filter((attribute) => !editable.has(attribute.code))
  const sectionProps = {
    values,
    form,
    inline,
    categories,
    onCategoryChange: state.onCategoryChange,
    newAttributes: inline.editingField === 'category_id' ? newAttributes : [],
    persistedUsages: values.usages,
  }

  return (
    <RecordSectionsGrid>
      <ProductIdentityRecordSection {...sectionProps} />
      <ProductClassificationRecordSection {...sectionProps} />
      <ProductPricingRecordSection {...sectionProps} />
      <ProductAttributesSection
        attributes={attributes}
        editable={editable}
        layout={product.attribute_layout ?? null}
        values={values.attribute_values}
        inline={inline}
        control={form.control}
        canEdit={product.permissions.resource.update}
      />
      {product.is_stock_tracked === true && can(STOCK_VIEW_PERMISSION) ? <StockProductSection productId={product.id} /> : null}
      <CustomFieldRecordSections
        resource="products"
        values={values.custom_fields}
        inline={inline}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
