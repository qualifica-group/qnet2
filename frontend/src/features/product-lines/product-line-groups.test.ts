import { describe, expect, it } from 'vitest'
import { flattenProductLineGroups, groupProductLineRows } from '@/features/product-lines/product-line-groups'
import type { ProductLineRow } from '@/features/product-lines/types'

const ROOT_OF: Record<number, number> = { 11: 1, 12: 1, 21: 2 }
const rootFor = (row: ProductLineRow) =>
  row.root_category_id ?? (row.product_category_id === null ? null : (ROOT_OF[row.product_category_id] ?? null))

describe('product-line groups (spec 0416)', () => {
  it('folds the lines of one root into one group, roots in order of first appearance', () => {
    const groups = groupProductLineRows(
      [
        { root_category_id: null, product_category_id: 11 },
        { root_category_id: null, product_category_id: 21 },
        { root_category_id: null, product_category_id: 12 },
      ],
      rootFor,
    )

    expect(groups).toEqual([
      { rootCategoryId: 1, categoryIds: [11, 12] },
      { rootCategoryId: 2, categoryIds: [21] },
    ])
  })

  it('keeps every row without a known root as a group of its own', () => {
    const groups = groupProductLineRows(
      [
        { root_category_id: null, product_category_id: null },
        { root_category_id: null, product_category_id: null },
      ],
      rootFor,
    )

    expect(groups).toHaveLength(2)
  })

  it('flattens back to one line per category, an empty group to one incomplete line', () => {
    expect(
      flattenProductLineGroups([
        { rootCategoryId: 1, categoryIds: [11, 12] },
        { rootCategoryId: 2, categoryIds: [] },
      ]),
    ).toEqual([
      { root_category_id: 1, product_category_id: 11 },
      { root_category_id: 1, product_category_id: 12 },
      { root_category_id: 2, product_category_id: null },
    ])
  })
})
