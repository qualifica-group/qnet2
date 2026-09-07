import { useMemo } from 'react'
import type { TFunction } from 'i18next'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import {
  RecordCard,
  RecordField,
  RecordFieldList,
  RecordSection,
} from '@/components/detail/record-panel'
import { Form } from '@/components/ui/form'
import { AttributeLayoutRenderer } from '@/features/attributes/attribute-layout-renderer'
import type { AttributeLayoutFormShape, LayoutBlob } from '@/features/attributes/attribute-layout-types'
import { isEmptyCustomFieldValue } from '@/features/custom-fields/custom-fields-values'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import type { ApplicableAttribute } from '@/features/request-management/types'

interface ProductAttributeValuesSectionProps {
  /** The category's configured (context=product, form_mode=view) layout, spec 0062; `null` -> flat. */
  layout: LayoutBlob | null
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
 * Read-only "Attributes" section of the product detail view (spec 0061): one
 * field row per PRODUCT-context attribute that actually has a value. Renders
 * nothing when the product carries no attribute value (additive feature,
 * zero-cost for a product predating attribute assignment). This is the FLAT
 * fallback (AC-007) — same valued-only content as before, now in the record
 * kit's own card so it sits on the detail canvas exactly like the configured
 * layout's sections do, instead of a second kit's chrome next to them.
 */
function ProductAttributeValuesFlat({ attributes, values }: Omit<ProductAttributeValuesSectionProps, 'layout'>) {
  const { t } = useTranslation()
  const valued = attributes.filter((attribute) => !isEmptyCustomFieldValue(values[attribute.code] ?? null))

  if (valued.length === 0) {
    return null
  }

  return (
    <RecordCard>
      <div className="p-4">
        <RecordSection title={t('products.form.dynamicFields.title')} icon={<SlidersHorizontal />}>
          <RecordFieldList>
            {valued.map((attribute) => (
              <RecordField key={attribute.code} label={attribute.name}>
                {formatAttributeValue(attribute, values[attribute.code], t)}
              </RecordField>
            ))}
          </RecordFieldList>
        </RecordSection>
      </div>
    </RecordCard>
  )
}

/**
 * Configured-layout branch (spec 0062 AC-014): mounts the SAME agnostic
 * renderer the create/edit form uses, `mode="view"`/`readOnly`, seeded from a
 * throwaway RHF instance — this is a display-only mount, nothing here ever
 * submits. Shows every effective attribute of the configured sections (not
 * only the valued ones), matching the admin-configured structure.
 */
function ProductAttributeLayoutView({
  layout,
  attributes,
  values,
}: Required<Pick<ProductAttributeValuesSectionProps, 'layout' | 'attributes' | 'values'>>) {
  const form = useForm<AttributeLayoutFormShape>({ defaultValues: { attribute_values: values } })
  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute, 'product')),
    [attributes],
  )

  return (
    <Form {...form}>
      <AttributeLayoutRenderer
        layout={layout}
        attributes={effectiveAttributes}
        control={form.control}
        mode="view"
        readOnly
      />
    </Form>
  )
}

export function ProductAttributeValuesSection({ layout, attributes, values }: ProductAttributeValuesSectionProps) {
  if (layout && layout.sections.length > 0) {
    return <ProductAttributeLayoutView layout={layout} attributes={attributes} values={values} />
  }

  return <ProductAttributeValuesFlat attributes={attributes} values={values} />
}
