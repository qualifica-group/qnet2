import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { RecordSection } from '@/components/detail/record-panel'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { AttributeLayoutView } from '@/features/attributes/attribute-layout-view'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface ProductAttributeValuesSectionProps {
  /** The category's configured (context=product, form_mode=view) layout, spec 0062; `null` -> one flat list. */
  layout: LayoutBlob | null
  /** Reused as-is: the same `ApplicableAttribute` DTO the Opportunity work panel reads (spec 0061). */
  attributes: ApplicableAttribute[]
  values: Record<string, CustomFieldValue>
}

/**
 * Read-only category attributes of the product detail (spec 0061/0062), laid
 * out like the form through `AttributeLayoutView` — configured sections,
 * columns and widths, plain label/value pairs, never form controls. Only the
 * VALUED attributes are shown (a layout section left without one is
 * dropped); renders nothing when the product carries no attribute value.
 */
export function ProductAttributeValuesSection({ layout, attributes, values }: ProductAttributeValuesSectionProps) {
  const { t } = useTranslation()
  const valued = attributes.filter((attribute) => !isEmptyCustomFieldValue(values[attribute.code] ?? null))

  if (valued.length === 0) {
    return null
  }

  return (
    <RecordSection title={t('products.form.dynamicFields.title')} icon={<SlidersHorizontal />} full>
      <AttributeLayoutView layout={layout} attributes={valued} values={values} />
    </RecordSection>
  )
}
