import { describe, expect, it, vi } from 'vitest'
import { renderHook } from '@testing-library/react'
import type { DragEndEvent } from '@dnd-kit/core'
import { useLayoutConfiguratorActions } from '@/features/attributes/layout-configurator/use-layout-configurator-actions'
import { addRow, addSection, placeAttribute } from '@/features/attributes/layout-configurator/layout-configurator-tree'
import { PALETTE_DROPPABLE_ID, rowDroppableId } from '@/features/attributes/layout-configurator/layout-configurator-dnd'
import type { LayoutBlob } from '@/features/attributes/attribute-layout-types'

/**
 * Exercises the hook's `handleDragEnd` — the real `onDragEnd` wired into the
 * configurator's `DndContext` — with synthetic dnd-kit events (spec 0062
 * AC-009). Simulating an actual pointer drag over jsdom is impractical
 * (no real layout/collision geometry); this calls the SAME handler dnd-kit
 * would call, at the same seam, matching the task's guidance to test
 * reorder/move handlers directly.
 */

function dragEvent(activeId: string, overId: string | null): DragEndEvent {
  return {
    active: { id: activeId, data: { current: undefined }, rect: { current: { initial: null, translated: null } } },
    over: overId ? { id: overId, rect: {} as never, disabled: false, data: { current: undefined } } : null,
    delta: { x: 0, y: 0 },
    collisions: null,
    activatorEvent: new Event('pointerup'),
  } as unknown as DragEndEvent
}

describe('useLayoutConfiguratorActions.handleDragEnd', () => {
  it('AC-009: dragging a palette attribute onto a row places it there', () => {
    let blob = addSection({ sections: [] })
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    const onChange = vi.fn()

    const { result } = renderHook(() => useLayoutConfiguratorActions({ blob, onChange }))
    result.current.handleDragEnd(dragEvent('company_name', rowDroppableId(rowId)))

    expect(onChange).toHaveBeenCalledWith(
      expect.objectContaining({
        sections: [expect.objectContaining({ rows: [{ id: rowId, items: [{ attribute_code: 'company_name', width: 'full' }] }] })],
      }),
    )
  })

  it('AC-009: dragging a placed item onto the palette unplaces it', () => {
    let blob = addSection({ sections: [] })
    const sectionId = blob.sections[0].id
    blob = addRow(blob, sectionId)
    const rowId = blob.sections[0].rows[0].id
    blob = placeAttribute(blob, 'sku', { type: 'row', sectionId, rowId, beforeCode: null })
    const onChange = vi.fn()

    const { result } = renderHook(() => useLayoutConfiguratorActions({ blob, onChange }))
    result.current.handleDragEnd(dragEvent('sku', PALETTE_DROPPABLE_ID))

    const next = onChange.mock.calls[0][0] as LayoutBlob
    expect(next.sections[0].rows[0].items).toEqual([])
  })

  it('does nothing when dropped outside any droppable', () => {
    const blob: LayoutBlob = { sections: [] }
    const onChange = vi.fn()

    const { result } = renderHook(() => useLayoutConfiguratorActions({ blob, onChange }))
    result.current.handleDragEnd(dragEvent('sku', null))

    expect(onChange).not.toHaveBeenCalled()
  })

  it('does nothing when dropped back on itself (no-op)', () => {
    const blob: LayoutBlob = { sections: [] }
    const onChange = vi.fn()

    const { result } = renderHook(() => useLayoutConfiguratorActions({ blob, onChange }))
    result.current.handleDragEnd(dragEvent('sku', 'sku'))

    expect(onChange).not.toHaveBeenCalled()
  })
})
