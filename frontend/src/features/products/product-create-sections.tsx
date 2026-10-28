import { useMemo } from 'react'
import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { Skeleton } from '@/components/ui/skeleton'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { DisplayableAttribute } from '@/features/attributes/attribute-value-display'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import { ProductAttributesSection } from '@/features/products/product-attributes-section'
import type { ProductRecordValues } from '@/features/products/product-record'
import {
  ProductClassificationRecordSection,
  ProductIdentityRecordSection,
  ProductPricingRecordSection,
  ProductSuppliersRecordSection,
} from '@/features/products/product-record-sections'
import type { ProductCategoryPicker } from '@/features/products/use-product-category-picker'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** An effective attribute as the shared layout view displays it. */
function toDisplayable(attribute: EffectiveAttribute): DisplayableAttribute {
  return {
    id: attribute.id,
    code: attribute.code,
    name: attribute.name,
    type: attribute.type,
    config: attribute.config as Record<string, unknown> | null,
    sort_order: attribute.sort_order,
    options: attribute.options,
  }
}

interface ProductCreateSectionsProps {
  /** The draft as the closed rows read it (`useProductDraftValues`, shared with the header). */
  values: ProductRecordValues
  form: UseFormReturn<ProductFormValues>
  draft: InlineEdit
  categories: ProductCategoryPicker
  onCategoryChange: (categoryId: number) => void
  /** The picked category's PRODUCT-context attributes, and the create layout they follow (spec 0062). */
  attributes: EffectiveAttribute[]
  layout: LayoutBlob | null
  attributesLoading: boolean
}

/**
 * The create form as a replica of the product detail (spec 0218 D-6): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked
 * and opening on the same component the detail edits in place. The category's
 * attributes appear as rows once a category is picked. Nothing is saved per
 * row: the header's Salva validates and creates the whole draft.
 */
export function ProductCreateSections({
  values,
  form,
  draft,
  categories,
  onCategoryChange,
  attributes,
  layout,
  attributesLoading,
}: ProductCreateSectionsProps) {
  const { canResource } = useResourcePermissions()
  const displayable = useMemo(() => attributes.map(toDisplayable), [attributes])
  const editable = useMemo(() => new Map(attributes.map((attribute) => [attribute.code, attribute])), [attributes])
  const sectionProps = {
    values,
    form,
    inline: draft,
    categories,
    onCategoryChange,
    newAttributes: [],
    productId: null,
  }

  return (
    <RecordSectionsGrid>
      <ProductIdentityRecordSection {...sectionProps} />
      <ProductClassificationRecordSection {...sectionProps} />
      <ProductPricingRecordSection {...sectionProps} />
      <ProductSuppliersRecordSection {...sectionProps} />
      {attributesLoading ? (
        <Skeleton className={`h-9 w-full ${FULL_WIDTH_SECTION_CLASS}`} />
      ) : (
        <ProductAttributesSection
          attributes={displayable}
          editable={editable}
          layout={layout}
          values={values.attribute_values}
          inline={draft}
          control={form.control}
          canEdit={canResource('create')}
        />
      )}
      <CustomFieldRecordSections
        resource="products"
        values={values.custom_fields}
        inline={draft}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
