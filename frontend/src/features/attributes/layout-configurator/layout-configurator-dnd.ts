import { locateAttribute, locateRow, type DropTarget } from '@/features/attributes/layout-configurator/layout-configurator-tree'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'

/**
 * @dnd-kit id conventions for the multi-container configurator (AC-009): a
 * draggable's id IS its `attribute_code` (globally unique across the blob —
 * an attribute is either in the palette or placed exactly once, never both).
 * Droppable containers are namespaced so they never collide with a code.
 */

export const PALETTE_DROPPABLE_ID = 'attribute-layout-palette'
const ROW_DROPPABLE_PREFIX = 'attribute-layout-row:'

export function rowDroppableId(rowId: string): string {
  return `${ROW_DROPPABLE_PREFIX}${rowId}`
}

function parseRowDroppableId(id: string): string | null {
  return id.startsWith(ROW_DROPPABLE_PREFIX) ? id.slice(ROW_DROPPABLE_PREFIX.length) : null
}

/**
 * Resolves a dnd-kit `over.id` into the semantic drop target `placeAttribute`
 * consumes: dropping on the palette container unplaces the item; dropping on
 * a row's own droppable region (its empty space) appends to that row;
 * dropping on another item's id inserts just before it, inside whichever row
 * (or the palette) currently holds that item.
 */
export function resolveDropTarget(blob: LayoutBlob, overId: string): DropTarget {
  if (overId === PALETTE_DROPPABLE_ID) {
    return { type: 'palette' }
  }

  const rowId = parseRowDroppableId(overId)
  if (rowId) {
    const owner = locateRow(blob, rowId)
    return owner ? { type: 'row', sectionId: owner.sectionId, rowId, beforeCode: null } : { type: 'palette' }
  }

  // `overId` is another attribute's code: land just before it, in its container.
  const location = locateAttribute(blob, overId)
  return location
    ? { type: 'row', sectionId: location.sectionId, rowId: location.rowId, beforeCode: overId }
    : { type: 'palette' }
}
