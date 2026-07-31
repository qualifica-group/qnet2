import { describe, expect, it } from 'vitest'
import { estimateGridHeight } from '@/components/data-table/data-table-theme'

describe('estimateGridHeight', () => {
  it('sums header, rows and pagination panel at 100% UI scale', () => {
    // header 32 + 25 rows * 28 + pagination panel (row height, above the 22px floor)
    expect(estimateGridHeight(25, 1)).toBe(32 + 25 * 28 + 28)
  })

  it('scales with the user UI scale, like the grid theme it mirrors', () => {
    // factor 1.3: header 42 + 10 rows * 36 + panel 36
    expect(estimateGridHeight(10, 1.3)).toBe(42 + 10 * 36 + 36)
  })

  it('keeps the pagination panel at its 22px floor when rows are tiny', () => {
    // factor 0.5: header 16 + 1 row * 14 + panel floor 22
    expect(estimateGridHeight(1, 0.5)).toBe(16 + 14 + 22)
  })
})
