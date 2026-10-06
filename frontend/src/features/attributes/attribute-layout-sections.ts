import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'

const OTHER_INFORMATION_SECTION_ID = 'attribute-layout-renderer:other-information'

/** The only fields the section resolution reads off an attribute, so both the form (EffectiveAttribute) and the detail (applicable-attribute summaries) feed it. */
interface LayoutPlaceableAttribute {
  code: string
  sort_order: number
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
function buildOtherInformationSection(unplaced: LayoutPlaceableAttribute[], title: string): LayoutSection {
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

/**
 * The sections a configured layout renders, shared by the form renderer and
 * the read-only detail view so both lay the same attributes out identically:
 * the configured sections in `sort_order`, then a synthetic trailing section
 * for every attribute no section places.
 */
export function resolveLayoutSections(
  layout: LayoutBlob,
  attributes: LayoutPlaceableAttribute[],
  otherInformationTitle: string,
): LayoutSection[] {
  const sortedSections = [...layout.sections].sort((a, b) => a.sort_order - b.sort_order)
  const placedCodes = collectPlacedCodes(sortedSections)
  const unplaced = attributes
    .filter((attribute) => !placedCodes.has(attribute.code))
    .sort((a, b) => a.sort_order - b.sort_order)

  return unplaced.length > 0
    ? [...sortedSections, buildOtherInformationSection(unplaced, otherInformationTitle)]
    : sortedSections
}
