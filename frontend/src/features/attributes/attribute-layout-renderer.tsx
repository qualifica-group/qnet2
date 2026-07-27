import { useMemo } from 'react'
import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import { AttributeLayoutSection } from '@/features/attributes/attribute-layout-section'
import type {
  AttributeLayoutFormShape,
  LayoutBlob,
  LayoutFormMode,
  LayoutSection,
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

const OTHER_INFORMATION_SECTION_ID = 'attribute-layout-renderer:other-information'

function buildAttributesByCode(attributes: EffectiveAttribute[]): Map<string, EffectiveAttribute> {
  return new Map(attributes.map((attribute) => [attribute.code, attribute]))
}

function collectPlacedCodes(sections: LayoutSection[]): Set<string> {
  const placed = new Set<string>()
  for (const section of sections) {
    for (const row of section.rows) {
      for (const item of row.items) {
        placed.add(item.attribute_code)
      }
    }
  }
  return placed
}

/** Synthesizes the trailing "other information" section for unplaced attributes — never persisted, one attribute per row (spec `layout-contract` semantics). */
function buildOtherInformationSection(unplaced: EffectiveAttribute[], title: string): LayoutSection {
  return {
    id: OTHER_INFORMATION_SECTION_ID,
    title,
    description: null,
    variant: 'secondary',
    collapsible: true,
    default_collapsed: true,
    columns: 1,
    sort_order: Number.MAX_SAFE_INTEGER,
    rows: unplaced.map((attribute) => ({
      id: `${OTHER_INFORMATION_SECTION_ID}:${attribute.code}`,
      items: [{ attribute_code: attribute.code, width: 'full' }],
    })),
  }
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
  const sortedSections = [...layout.sections].sort((a, b) => a.sort_order - b.sort_order)
  const placedCodes = collectPlacedCodes(sortedSections)
  const unplaced = attributes
    .filter((attribute) => !placedCodes.has(attribute.code))
    .sort((a, b) => a.sort_order - b.sort_order)
  const otherInformationTitle = t('attributes.layout.otherInformation', { defaultValue: 'Altre informazioni' })

  return (
    <div className="flex flex-col gap-4">
      {sortedSections.map((section) => (
        <AttributeLayoutSection
          key={section.id}
          section={section}
          attributesByCode={attributesByCode}
          control={control}
          disabled={disabled}
          readOnly={effectiveReadOnly}
        />
      ))}
      {unplaced.length > 0 ? (
        <AttributeLayoutSection
          key={OTHER_INFORMATION_SECTION_ID}
          section={buildOtherInformationSection(unplaced, otherInformationTitle)}
          attributesByCode={attributesByCode}
          control={control}
          disabled={disabled}
          readOnly={effectiveReadOnly}
        />
      ) : null}
    </div>
  )
}
