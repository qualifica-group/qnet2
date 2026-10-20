import type { LayoutBlob, LayoutSection } from '@/features/attributes/attribute-layout-types'

/**
 * The single FE implementation of the conditional-section rule (spec 0288 D-3),
 * mirroring backend `App\RequestManagement\AttributeSectionVisibility`.
 * A section with `visible_when` is visible only if the control attribute's
 * value is strictly `true` AND the section that places the control is visible
 * (chains can only arise from merged multi-category layouts). A control that
 * is not placed in the layout, or a cycle, hides the section. Sections without
 * a condition (and the synthetic "other information" one, which is not in the
 * blob) are always visible.
 */

function controlSectionsByCode(layout: LayoutBlob): Map<string, LayoutSection> {
  const byCode = new Map<string, LayoutSection>()
  for (const section of layout.sections) {
    for (const row of section.rows) {
      for (const item of row.items) {
        byCode.set(item.attribute_code, section)
      }
    }
  }
  return byCode
}

function evaluate(
  section: LayoutSection,
  byCode: ReadonlyMap<string, LayoutSection>,
  values: Record<string, unknown>,
  visiting: ReadonlySet<string>,
): boolean {
  const condition = section.visible_when
  if (!condition) {
    return true
  }
  const container = byCode.get(condition.attribute_code)
  if (!container || visiting.has(section.id) || values[condition.attribute_code] !== true) {
    return false
  }
  return evaluate(container, byCode, values, new Set(visiting).add(section.id))
}

/** Ids of the configured sections hidden for these values (empty without a layout). */
export function hiddenSectionIds(layout: LayoutBlob | null, values: Record<string, unknown>): Set<string> {
  const hidden = new Set<string>()
  if (!layout) {
    return hidden
  }
  const byCode = controlSectionsByCode(layout)
  for (const section of layout.sections) {
    if (!evaluate(section, byCode, values, new Set())) {
      hidden.add(section.id)
    }
  }
  return hidden
}

/**
 * Whether `section` shows for these values. Pass the same `layout` the section
 * comes from (it resolves the control's container); a section not in it
 * (the synthetic trailing one) is always visible.
 */
export function isSectionVisible(
  layout: LayoutBlob | null,
  section: LayoutSection,
  values: Record<string, unknown>,
): boolean {
  return !hiddenSectionIds(layout, values).has(section.id)
}

/** Codes of every attribute placed in a hidden section: their required rules do not apply. */
export function hiddenAttributeCodes(layout: LayoutBlob | null, values: Record<string, unknown>): Set<string> {
  const codes = new Set<string>()
  if (!layout) {
    return codes
  }
  const hidden = hiddenSectionIds(layout, values)
  for (const section of layout.sections) {
    if (!hidden.has(section.id)) {
      continue
    }
    for (const row of section.rows) {
      for (const item of row.items) {
        codes.add(item.attribute_code)
      }
    }
  }
  return codes
}
