import { describe, expect, it } from 'vitest'
import { gridMountKey } from '@/features/table/grid-mount-key'

describe('gridMountKey (AC-016)', () => {
  it('changes when the list of attribute categories changes', () => {
    expect(gridMountKey(0, [5])).not.toBe(gridMountKey(0, [6]))
    expect(gridMountKey(0, [5])).not.toBe(gridMountKey(0, [5, 6]))
    expect(gridMountKey(0, [5])).not.toBe(gridMountKey(0, []))
  })

  it('is stable for the same list, and an absent list equals an empty one', () => {
    expect(gridMountKey(0, [5, 6])).toBe(gridMountKey(0, [5, 6]))
    expect(gridMountKey(0, [])).toBe(gridMountKey(0, undefined))
  })

  it('changes on a layout reset', () => {
    expect(gridMountKey(0, [5])).not.toBe(gridMountKey(1, [5]))
  })
})
