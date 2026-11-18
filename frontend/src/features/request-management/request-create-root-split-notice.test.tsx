import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { RequestCreateRootSplitNotice } from '@/features/request-management/request-create-root-split-notice'
import { rootSplitNames } from '@/features/request-management/root-split-names'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0411 (user request 2026-11-18): the create form warns that lines of
 * several parent categories create one request each.
 */

function node(id: number, name: string, parentId: number | null, children: ProductCategoryTreeNode[] = []): ProductCategoryTreeNode {
  // Asserted, not annotated: the tree node type gains fields in parallel work (spec 0410).
  return {
    id,
    name,
    parent_id: parentId,
    children,
    attributes_count: 0,
    products_count: 0,
    business_function_id: 1,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: false,
    multi_quote_work_order: false,
    usage_area: 'all',
    multiple_open_opportunities: false,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
  } as ProductCategoryTreeNode
}

const TREE: ProductCategoryTreeNode[] = [
  node(1, 'ISO', null, [node(11, 'ISO 9001', 1), node(12, 'ISO 14001', 1)]),
  node(2, 'APL', null, [node(21, 'Tirocinio', 2)]),
]

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: TREE }),
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('rootSplitNames', () => {
  it('groups the rows by parent category in order of first appearance', () => {
    expect(
      rootSplitNames(
        [
          { root_category_id: null, product_category_id: 21 },
          { root_category_id: null, product_category_id: 11 },
          { root_category_id: 1, product_category_id: 12 },
        ],
        TREE,
      ),
    ).toEqual(['APL', 'ISO'])
  })

  it('counts a row with only its parent category picked, never an empty one', () => {
    expect(
      rootSplitNames(
        [
          { root_category_id: 2, product_category_id: null },
          { root_category_id: null, product_category_id: null },
        ],
        TREE,
      ),
    ).toEqual(['APL'])
  })
})

describe('RequestCreateRootSplitNotice', () => {
  it('announces one request per parent category when the lines span two of them', () => {
    render(
      <RequestCreateRootSplitNotice
        rows={[
          { root_category_id: null, product_category_id: 11 },
          { root_category_id: null, product_category_id: 21 },
        ]}
      />,
    )

    expect(screen.getByRole('status')).toHaveTextContent(
      'Note: the product lines belong to 2 parent categories (ISO, APL). Saving will create 2 requests, one per parent category, on the same opportunity.',
    )
  })

  it('stays silent when every line is under the same parent category', () => {
    render(
      <RequestCreateRootSplitNotice
        rows={[
          { root_category_id: null, product_category_id: 11 },
          { root_category_id: null, product_category_id: 12 },
        ]}
      />,
    )

    expect(screen.queryByRole('status')).toBeNull()
  })
})
