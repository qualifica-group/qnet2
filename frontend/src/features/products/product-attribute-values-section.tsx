import type { TFunction } from 'i18next'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface ProductAttributeValuesSectionProps {
  /** The category's configured (context=product, form_mode=view) layout, spec 0062; `null` -> one flat section. */
  layout: LayoutBlob | null
  /** Reused as-is: the same `ApplicableAttribute` DTO the Opportunity work panel reads (spec 0061). */
  attributes: ApplicableAttribute[]
  values: Record<string, CustomFieldValue>
}

interface AttributeGroup {
  key: string
  title: string
  attributes: ApplicableAttribute[]
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
 * Groups the valued attributes by the configured layout: one group per layout
 * section (in `sort_order`, items in row order), then the valued attributes no
 * section places under "Other information". Without a layout, a single group.
 * Groups left without a valued attribute are dropped.
 */
function groupValuedAttributes(
  { layout, attributes, values }: ProductAttributeValuesSectionProps,
  t: TFunction,
): AttributeGroup[] {
  const valued = attributes.filter((attribute) => !isEmptyCustomFieldValue(values[attribute.code] ?? null))
  const otherTitle = t('attributes.layout.otherInformation', { defaultValue: 'Altre informazioni' })

  if (!layout || layout.sections.length === 0) {
    return valued.length > 0
      ? [{ key: 'flat', title: t('products.form.dynamicFields.title'), attributes: valued }]
      : []
  }

  const byCode = new Map(valued.map((attribute) => [attribute.code, attribute]))
  const placed = new Set<string>()
  const groups = [...layout.sections]
    .sort((left, right) => left.sort_order - right.sort_order)
    .map((section) => {
      const codes = section.rows.flatMap((row) => row.items.map((item) => item.attribute_code))
      codes.forEach((code) => placed.add(code))
      return {
        key: section.id,
        title: section.title,
        attributes: codes.flatMap((code) => byCode.get(code) ?? []),
      }
    })

  groups.push({ key: 'other', title: otherTitle, attributes: valued.filter((attribute) => !placed.has(attribute.code)) })

  return groups.filter((group) => group.attributes.length > 0)
}

/**
 * Read-only category attributes of the product detail (spec 0061/0062), as
 * plain label/value rows inside the product record card — never form
 * controls: the detail is not an edit surface. Renders nothing when the
 * product carries no attribute value.
 */
export function ProductAttributeValuesSection(props: ProductAttributeValuesSectionProps) {
  const { t } = useTranslation()
  const groups = groupValuedAttributes(props, t)

  return groups.map((group) => (
    <RecordSection key={group.key} title={group.title} icon={<SlidersHorizontal />}>
      <RecordFieldList>
        {group.attributes.map((attribute) => (
          <RecordField key={attribute.code} label={attribute.name}>
            {formatAttributeValue(attribute, props.values[attribute.code], t)}
          </RecordField>
        ))}
      </RecordFieldList>
    </RecordSection>
  ))
}
