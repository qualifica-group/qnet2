import { useMemo } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import { AttributeLayoutSection } from '@/features/attributes/attribute-layout-section'
import { resolveLayoutSections } from '@/features/attributes/attribute-layout-sections'
import type {
  AttributeLayoutFormShape,
  LayoutBlob,
  LayoutFormMode,
} from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Module-agnostic layout renderer (spec 0062 MT-2.1, goal: "riusabile in
 * futuro altrove"): given a category's resolved `layout` and its effective
 * attributes, renders sections -> rows -> grid, each item bound through
 * `AttributeLayoutField` to `attribute_values.<code>`. The SAME component
 * backs the configurator's live preview (AC-011), the Product form/detail
 * and the Opportunity work-panel (future wiring, out of this task's scope —
 * `features/products`/`features/request-management` stay untouched here).
 *
 * `layout === null` (or an empty `sections` array) falls back to the flat,
 * one-attribute-per-row rendering that predates this spec (AC-007): every
 * effective attribute, sorted by `sort_order`, with no section grouping —
 * byte-for-byte the same field markup as `ProductDynamicFields`.
 */

interface AttributeLayoutRendererProps<TFieldValues extends AttributeLayoutFormShape> {
  layout: LayoutBlob | null
  attributes: EffectiveAttribute[]
  control: Control<TFieldValues>
  mode: LayoutFormMode
  /** Hard-disabled (e.g. no update permission). Default `false`. */
  disabled?: boolean
  /** Editable=false but not disabled. Forced `true` when `mode === 'view'`. Default `false`. */
  readOnly?: boolean
}

function buildAttributesByCode(attributes: EffectiveAttribute[]): Map<string, EffectiveAttribute> {
  return new Map(attributes.map((attribute) => [attribute.code, attribute]))
}

export function AttributeLayoutRenderer<TFieldValues extends AttributeLayoutFormShape>({
  layout,
  attributes,
  control,
  mode,
  disabled = false,
  readOnly = false,
}: AttributeLayoutRendererProps<TFieldValues>) {
  const { t } = useTranslation()
  const effectiveReadOnly = readOnly || mode === 'view'
  const attributesByCode = useMemo(() => buildAttributesByCode(attributes), [attributes])

  // Step 1: no persisted layout -> flat fallback (AC-007 regression contract)
  if (!layout || layout.sections.length === 0) {
    return (
      <div className="flex flex-col gap-4">
        {[...attributes]
          .sort((a, b) => a.sort_order - b.sort_order)
          .map((attribute) => (
            <AttributeLayoutField
              key={attribute.code}
              control={control}
              attribute={attribute}
              disabled={disabled}
              readOnly={effectiveReadOnly}
            />
          ))}
      </div>
    )
  }

  // Step 2: configured sections, sorted, then a synthetic trailing section for whatever is left unplaced
  const otherInformationTitle = t('attributes.layout.otherInformation', { defaultValue: 'Altre informazioni' })
  const sections = resolveLayoutSections(layout, attributes, otherInformationTitle)

  return (
    <div className="flex flex-col gap-4">
      {sections.map((section) => (
        <AttributeLayoutSection
          key={section.id}
          section={section}
          attributesByCode={attributesByCode}
          control={control}
          disabled={disabled}
          readOnly={effectiveReadOnly}
        />
      ))}
    </div>
  )
}
