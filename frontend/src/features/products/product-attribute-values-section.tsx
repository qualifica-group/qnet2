import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { DetailField, DetailGrid, DetailSection } from '@/components/detail/detail-panel'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface ProductAttributeValuesSectionProps {
  /** Reused as-is: the same `ApplicableAttribute` DTO the Opportunity work panel reads (spec 0061). */
  attributes: ApplicableAttribute[]
  values: Record<string, CustomFieldValue>
}

/** Renders an `enum`/`relation` scalar value through its option label (or the raw id, unresolved for `relation`). */
function formatScalar(attribute: ApplicableAttribute, value: string | number, t: TFunction): string {
  if (attribute.type === 'boolean') {
    return value ? t('common.yes') : t('common.no')
  }
  if (attribute.type === 'enum') {
    return attribute.options.find((option) => option.value === String(value))?.label ?? String(value)
  }
  if (attribute.type === 'relation') {
    return `#${value}`
  }
  return String(value)
}

/** Read-only formatting for one attribute value (additive, spec 0061): array values (multiselect/many-relation) join their formatted members. */
function formatAttributeValue(attribute: ApplicableAttribute, value: CustomFieldValue, t: TFunction): string {
  if (Array.isArray(value)) {
    return value.map((item) => formatScalar(attribute, item, t)).join(', ')
  }
  if (typeof value === 'boolean') {
    return value ? t('common.yes') : t('common.no')
  }
  return formatScalar(attribute, value as string | number, t)
}

/**
 * Read-only "Attributes" section of the product detail view (spec 0061):
 * one `DetailField` per PRODUCT-context attribute that actually has a value.
 * Renders nothing when the product carries no attribute value (additive
 * feature, zero-code for a product predating attribute assignment).
 */
export function ProductAttributeValuesSection({ attributes, values }: ProductAttributeValuesSectionProps) {
  const { t } = useTranslation()
  const valued = attributes.filter((attribute) => !isEmptyCustomFieldValue(values[attribute.code] ?? null))

  if (valued.length === 0) {
    return null
  }

  return (
    <DetailSection title={t('products.form.dynamicFields.title')}>
      <DetailGrid>
        {valued.map((attribute) => (
          <DetailField key={attribute.code} label={attribute.name}>
            {formatAttributeValue(attribute, values[attribute.code], t)}
          </DetailField>
        ))}
      </DetailGrid>
    </DetailSection>
  )
}
