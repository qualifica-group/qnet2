import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'
import type { EffectiveAttribute } from '@/features/product-categories/types'

/**
 * Configuration rules for conditional sections (spec 0288 D-2/D-7), mirroring
 * the server validator: the control attribute is a `boolean`, placed in this
 * layout, outside the section it controls, in a section with no condition of
 * its own (no chains). Pure so the Select options and the auto-reset are
 * unit-testable without mounting the configurator.
 */

/** attribute_code -> id of the section that places it. */
function sectionIdByCode(blob: LayoutBlob): Map<string, string> {
  const byCode = new Map<string, string>()
  for (const section of blob.sections) {
    for (const row of section.rows) {
      for (const item of row.items) {
        byCode.set(item.attribute_code, section.id)
      }
    }
  }
  return byCode
}

/** The boolean attributes `sectionId` may use as its condition, sort_order-stable. */
export function allowedConditionAttributes(
  blob: LayoutBlob,
  sectionId: string,
  attributes: EffectiveAttribute[],
): EffectiveAttribute[] {
  const placedIn = sectionIdByCode(blob)
  const unconditionalSectionIds = new Set(
    blob.sections.filter((section) => !section.visible_when).map((section) => section.id),
  )
  return attributes
    .filter((attribute) => {
      const containerId = placedIn.get(attribute.code)
      return (
        attribute.type === 'boolean' &&
        containerId !== undefined &&
        containerId !== sectionId &&
        unconditionalSectionIds.has(containerId)
      )
    })
    .sort((a, b) => a.sort_order - b.sort_order)
}

/** Whether some other section is conditioned on an attribute placed in `sectionId` (it then cannot become conditional). */
export function controlsOtherSections(blob: LayoutBlob, sectionId: string): boolean {
  const placedIn = sectionIdByCode(blob)
  return blob.sections.some(
    (section) =>
      section.visible_when != null && placedIn.get(section.visible_when.attribute_code) === sectionId,
  )
}

/**
 * Resets every condition that no longer satisfies D-2 after the tree changed:
 * the control left the layout, moved into the section it controls, or moved
 * into a conditional section. Returns the same blob when nothing changed.
 */
export function clearBrokenConditions(blob: LayoutBlob): LayoutBlob {
  const placedIn = sectionIdByCode(blob)
  const conditionalIds = new Set(blob.sections.filter((section) => section.visible_when).map((section) => section.id))
  let changed = false
  const sections = blob.sections.map((section) => {
    if (!section.visible_when) {
      return section
    }
    const containerId = placedIn.get(section.visible_when.attribute_code)
    if (containerId !== undefined && containerId !== section.id && !conditionalIds.has(containerId)) {
      return section
    }
    changed = true
    return { ...section, visible_when: null }
  })
  return changed ? { sections } : blob
}
