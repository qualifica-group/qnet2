import type { DragEndEvent } from '@dnd-kit/core'
import { resolveDropTarget } from '@/features/attributes/layout-configurator/layout-configurator-dnd'
import {
  addRow,
  addSection,
  moveRow,
  moveSection,
  placeAttribute,
  removeRow,
  removeSection,
  updateItemWidth,
  updateSection,
  type SectionPatch,
} from '@/features/attributes/layout-configurator/layout-configurator-tree'
import type { LayoutBlob, LayoutItemWidth } from '@/features/attributes/attribute-layout-types'

export interface LayoutConfiguratorActions {
  addSection: () => void
  removeSection: (sectionId: string) => void
  updateSection: (sectionId: string, patch: SectionPatch) => void
  moveSectionUp: (sectionId: string) => void
  moveSectionDown: (sectionId: string) => void
  addRow: (sectionId: string) => void
  removeRow: (sectionId: string, rowId: string) => void
  moveRowUp: (sectionId: string, rowId: string) => void
  moveRowDown: (sectionId: string, rowId: string) => void
  updateItemWidth: (attributeCode: string, width: LayoutItemWidth) => void
  removeItemToPalette: (attributeCode: string) => void
  handleDragEnd: (event: DragEndEvent) => void
}

interface UseLayoutConfiguratorActionsArgs {
  blob: LayoutBlob
  onChange: (next: LayoutBlob) => void
}

/**
 * Thin, fully-controlled wrapper around `layout-configurator-tree.ts`'s pure
 * functions: reads the current `blob` prop fresh on every render (no local
 * state, no stale closures) and reports every mutation upward via `onChange`
 * — the single source of truth stays in the caller (spec 0062 MT-3.1's
 * "stato locale dell'albero", owned one level up so the mount can also feed
 * it into the save mutation and the live preview, AC-011).
 */
export function useLayoutConfiguratorActions({
  blob,
  onChange,
}: UseLayoutConfiguratorActionsArgs): LayoutConfiguratorActions {
  return {
    addSection: () => onChange(addSection(blob)),
    removeSection: (sectionId) => onChange(removeSection(blob, sectionId)),
    updateSection: (sectionId, patch) => onChange(updateSection(blob, sectionId, patch)),
    moveSectionUp: (sectionId) => onChange(moveSection(blob, sectionId, 'up')),
    moveSectionDown: (sectionId) => onChange(moveSection(blob, sectionId, 'down')),
    addRow: (sectionId) => onChange(addRow(blob, sectionId)),
    removeRow: (sectionId, rowId) => onChange(removeRow(blob, sectionId, rowId)),
    moveRowUp: (sectionId, rowId) => onChange(moveRow(blob, sectionId, rowId, 'up')),
    moveRowDown: (sectionId, rowId) => onChange(moveRow(blob, sectionId, rowId, 'down')),
    updateItemWidth: (attributeCode, width) => onChange(updateItemWidth(blob, attributeCode, width)),
    removeItemToPalette: (attributeCode) => onChange(placeAttribute(blob, attributeCode, { type: 'palette' })),
    handleDragEnd: (event) => {
      // Step 1: ignore drops outside a droppable, or a no-op drop on itself
      const { active, over } = event
      if (!over || active.id === over.id) {
        return
      }
      // Step 2: resolve the drop target and apply the move
      const target = resolveDropTarget(blob, String(over.id))
      onChange(placeAttribute(blob, String(active.id), target))
    },
  }
}
