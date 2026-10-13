import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { AttributeLayoutView, type AttributeFieldRenderer } from '@/features/attributes/attribute-layout-view'
import type { DisplayableAttribute } from '@/features/attributes/attribute-value-display'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { ProductFormValues } from '@/features/products/use-product-form'

interface ProductAttributesSectionProps {
  /** The category's PRODUCT-context attributes as the rows display them. */
  attributes: DisplayableAttribute[]
  /** The same attributes as the field leaf edits them, keyed by code. */
  editable: ReadonlyMap<string, EffectiveAttribute>
  /** The category's configured layout (spec 0062); `null` -> one flat list. */
  layout: LayoutBlob | null
  /** The values the closed rows show: persisted on the detail, the draft's on create. */
  values: Record<string, unknown>
  inline: InlineEdit
  control: Control<ProductFormValues>
  /** Attribute values are authorized at the resource level (spec 0218 D-5), not per field. */
  canEdit: boolean
}

/**
 * "Attributi" of the product record (spec 0061/0062, spec 0218): the
 * configured sections, columns and widths of `AttributeLayoutView`, every
 * attribute — valued or not — a row that edits in place on the SAME field
 * leaf the attribute forms render (`AttributeLayoutField`). On the detail a
 * confirm PATCHes that attribute alone; on a create draft it only keeps it.
 * Absent when the category resolves no attribute.
 */
export function ProductAttributesSection({
  attributes,
  editable,
  layout,
  values,
  inline,
  control,
  canEdit,
}: ProductAttributesSectionProps) {
  const { t } = useTranslation()

  if (attributes.length === 0) {
    return null
  }

  const renderField: AttributeFieldRenderer = (attribute, value, fieldClassName) => {
    const effective = editable.get(attribute.code)
    return (
      <RecordInlineField
        field={`attribute_values.${attribute.code}`}
        label={attribute.name}
        inline={inline}
        canEdit={canEdit && effective !== undefined}
        className={fieldClassName}
        editor={
          effective ? (
            <AttributeLayoutField control={control} attribute={effective} disabled={false} readOnly={false} hideLabel />
          ) : null
        }
      >
        {value}
      </RecordInlineField>
    )
  }

  return (
    <RecordSection title={t('products.form.dynamicFields.title')} icon={<SlidersHorizontal />} full>
      <AttributeLayoutView layout={layout} attributes={attributes} values={values} renderField={renderField} />
    </RecordSection>
  )
}
