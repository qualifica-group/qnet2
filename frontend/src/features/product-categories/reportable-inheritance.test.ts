import { describe, expect, it } from 'vitest'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  reportableOverrideFor,
  resolveInheritedReportable,
} from '@/features/product-categories/reportable-inheritance'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

function node(overrides: Partial<ProductCategoryTreeNode>): ProductCategoryTreeNode {
  return {
    id: 1,
    name: 'Node',
    parent_id: null,
    children: [],
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_reportable: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: true,
    simplified_offer_line: false,
    ...overrides,
  }
}

const tree = [
  node({
    id: 1,
    name: 'GOL',
    is_reportable: true,
    children: [
      node({
        id: 2,
        name: 'GOL - Campania',
        parent_id: 1,
        children: [node({ id: 3, name: 'Corso', parent_id: 2 })],
      }),
      node({ id: 4, name: 'GOL - Lombardia', parent_id: 1, is_reportable: false }),
    ],
  }),
  node({ id: 5, name: 'Consulenza' }),
]

describe('resolveInheritedReportable', () => {
  const nodesById = indexCategoryTree(tree)

  it('returns null for a root (nothing to inherit)', () => {
    expect(resolveInheritedReportable(nodesById, null)).toBeNull()
  })

  it('takes the nearest ancestor override, skipping inheriting nodes', () => {
    expect(resolveInheritedReportable(nodesById, 2)).toEqual({
      value: true,
      sourceCategory: { id: 1, name: 'GOL' },
    })
  })

  it('passes a forced-off ancestor on to its subtree', () => {
    expect(resolveInheritedReportable(nodesById, 4)).toEqual({
      value: false,
      sourceCategory: { id: 4, name: 'GOL - Lombardia' },
    })
  })

  it('is false with no source when nothing up the chain sets it', () => {
    expect(resolveInheritedReportable(nodesById, 5)).toEqual({ value: false, sourceCategory: null })
  })
})

describe('reportableOverrideFor', () => {
  const inheritedOn = { value: true, sourceCategory: { id: 1, name: 'GOL' } }

  it('stores null when the switch matches the inherited value', () => {
    expect(reportableOverrideFor(true, inheritedOn)).toBeNull()
  })

  it('forces the value when it diverges from the inherited one', () => {
    expect(reportableOverrideFor(false, inheritedOn)).toBe(false)
  })

  it('on a root, on is forced and off inherits', () => {
    expect(reportableOverrideFor(true, null)).toBe(true)
    expect(reportableOverrideFor(false, null)).toBeNull()
  })
})
