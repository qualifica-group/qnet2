import { describe, expect, it } from 'vitest'
import { gridColsClass, itemSpanClass, LAYOUT_GRID_GAP_CLASS } from '@/features/attributes/attribute-layout-grid'
import { LAYOUT_COLUMNS_OPTIONS, LAYOUT_ITEM_WIDTHS, type LayoutColumns } from '@/features/attributes/attribute-layout-types'

/**
 * Spec 0062 AC-013: classes are STATIC (this test only reads literal strings
 * from the lookup, never builds one), and the grid collapses N -> .. -> 1
 * column at the documented CONTAINER-query breakpoints (`@`-variants — the
 * grid responds to the panel width, not the viewport). Numeric spans are
 * cross-checked against the frozen formula (`layout-contract` semantics):
 * full=columns, two_thirds=ceil(2*columns/3), half=ceil(columns/2),
 * third=ceil(columns/3).
 */

function expectedFraction(columns: number, width: (typeof LAYOUT_ITEM_WIDTHS)[number]): number {
  if (width === 'full') return columns
  if (width === 'two_thirds') return Math.ceil((2 * columns) / 3)
  if (width === 'half') return Math.ceil(columns / 2)
  if (width === 'third') return Math.ceil(columns / 3)
  return Math.ceil(columns / 4)
}

/** The effective span once every tier has cascaded: the LAST `col-span-N` (tiers are listed ascending, base -> @md -> wide). */
function largestSpan(className: string): number {
  const matches = [...className.matchAll(/col-span-(\d+)/g)]
  return Number(matches.at(-1)?.[1])
}

describe('attribute-layout-grid', () => {
  it('grid-cols always starts at 1 (mobile) and reaches the section columns at desktop', () => {
    for (const columns of LAYOUT_COLUMNS_OPTIONS) {
      const classes = gridColsClass(columns)
      expect(classes.startsWith('grid-cols-1')).toBe(true)
      expect(classes).toContain(`grid-cols-${columns}`)
    }
  })

  it('columns=1 never needs a responsive override (stays 1 col at every breakpoint)', () => {
    expect(gridColsClass(1)).toBe('grid-cols-1')
  })

  it('columns=4 collapses through an intermediate 2-col step before the 4-col wide-container grid', () => {
    expect(gridColsClass(4)).toBe('grid-cols-1 @xs:grid-cols-2 @md:grid-cols-4')
  })

  it('every (columns, width) pair starts at col-span-1 on mobile', () => {
    for (const columns of LAYOUT_COLUMNS_OPTIONS) {
      for (const width of LAYOUT_ITEM_WIDTHS) {
        expect(itemSpanClass(columns, width).startsWith('col-span-1')).toBe(true)
      }
    }
  })

  it('the largest emitted span matches the frozen width formula, clamped to columns', () => {
    for (const columns of LAYOUT_COLUMNS_OPTIONS) {
      for (const width of LAYOUT_ITEM_WIDTHS) {
        const expected = Math.min(expectedFraction(columns, width), columns)
        expect(largestSpan(itemSpanClass(columns, width))).toBe(expected)
      }
    }
  })

  it('never emits a span above the section columns for a given breakpoint', () => {
    const spanAtColumns: Record<LayoutColumns, number> = { 1: 1, 2: 2, 3: 3, 4: 4 }
    for (const columns of LAYOUT_COLUMNS_OPTIONS) {
      for (const width of LAYOUT_ITEM_WIDTHS) {
        expect(largestSpan(itemSpanClass(columns, width))).toBeLessThanOrEqual(spanAtColumns[columns])
      }
    }
  })

  it('exposes a compact static gap class', () => {
    expect(LAYOUT_GRID_GAP_CLASS).toBe('gap-3')
  })
})
