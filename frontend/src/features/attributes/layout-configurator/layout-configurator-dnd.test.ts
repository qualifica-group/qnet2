import { describe, expect, it } from 'vitest'
import {
  PALETTE_DROPPABLE_ID,
  resolveDropTarget,
  rowDroppableId,
} from '@/features/attributes/layout-configurator/layout-configurator-dnd'
import { addRow, addSection, placeAttribute } from '@/features/attributes/layout-configurator/layout-configurator-tree'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'

describe('resolveDropTarget', () => {
  it('resolves the palette droppable id to a palette target', () => {
    const blob: LayoutBlob = { sections: [] }
    expect(resolveDropTarget(blob, PALETTE_DROPPABLE_ID)).toEqual({ type: 'palette' })
  })

  it('resolves a row droppable id (empty space) to an append-at-end row target', () => {
    let blob = addSection({ sections: [] })
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id

    expect(resolveDropTarget(blob, rowDroppableId(rowId))).toEqual({
      type: 'row',
      sectionId,
      rowId,
      beforeCode: null,
    })
  })

  it('resolves an item id (attribute_code) to an insert-before target in its owning row', () => {
    let blob = addSection({ sections: [] })
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId, beforeCode: null })

    expect(resolveDropTarget(blob, 'sku')).toEqual({ type: 'row', sectionId, rowId, beforeCode: 'sku' })
  })

  it('falls back to the palette for an unknown/stale id', () => {
    const blob: LayoutBlob = { sections: [] }
    expect(resolveDropTarget(blob, 'not-a-real-code')).toEqual({ type: 'palette' })
  })
})
