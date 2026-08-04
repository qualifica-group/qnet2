import { describe, expect, it } from 'vitest'
import {
  MANAGER_LABEL_MAX_POSITION,
  nextFreeManagerLabelPosition,
  padManagerLabelPositions,
} from '@/features/product-categories/product-category-schema'

/** Spec 0080 amendment A1: the dynamic manager-labels section's position math. */

describe('nextFreeManagerLabelPosition', () => {
  it('returns 1 for an empty set', () => {
    expect(nextFreeManagerLabelPosition([])).toBe(1)
  })

  it('returns the smallest gap, not max+1', () => {
    expect(nextFreeManagerLabelPosition([1, 3, 4])).toBe(2)
  })

  it('returns max+1 when there is no gap', () => {
    expect(nextFreeManagerLabelPosition([1, 2, 3])).toBe(4)
  })

  it('returns null once every position up to the ceiling is used (AC-055)', () => {
    const allTwelve = Array.from({ length: MANAGER_LABEL_MAX_POSITION }, (_, index) => index + 1)
    expect(nextFreeManagerLabelPosition(allTwelve)).toBeNull()
  })

  it('the ceiling is 12 (spec 0080 A1 D2, aligned with ValidatesManagerSlots::MAX_MANAGER_SLOTS)', () => {
    expect(MANAGER_LABEL_MAX_POSITION).toBe(12)
  })
})

describe('padManagerLabelPositions', () => {
  it('pads an empty list up to the minimum with the smallest positions', () => {
    expect(padManagerLabelPositions([], 4)).toEqual([1, 2, 3, 4])
  })

  it('never drops positions already present, even beyond the minimum', () => {
    expect(padManagerLabelPositions([1, 3, 6], 4)).toEqual([1, 2, 3, 6])
  })

  it('is a no-op once the minimum is already met or exceeded', () => {
    expect(padManagerLabelPositions([1, 5, 9, 12], 4)).toEqual([1, 5, 9, 12])
  })

  it('stops at the ceiling instead of padding past it', () => {
    const nineUsed = Array.from({ length: 9 }, (_, index) => index + 1)
    expect(padManagerLabelPositions(nineUsed, 20)).toEqual(Array.from({ length: 12 }, (_, index) => index + 1))
  })
})
