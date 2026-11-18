import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryRootSelect } from '@/features/product-lines/product-category-root-select'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0411 D-4: a request bound to one parent category offers only that root
 * to its product-line rows; a root already saved on the row stays listed.
 */

function root(id: number): ProductCategoryTreeNode {
  // Asserted, not annotated: the tree node type gains fields in parallel work (spec 0410).
  return {
    id,
    name: `Root ${id}`,
    parent_id: null,
    children: [],
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

const TREE: ProductCategoryTreeNode[] = [root(1), root(2), root(3)]

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: TREE, isPending: false, isError: false, refetch: vi.fn() }),
}))

type Option = { id: number }
const optionsMock = vi.fn<(options: Option[]) => void>()
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({ options }: { options: Option[] }) => {
    optionsMock(options)
    return null
  },
}))

function renderRootSelect(onlyRootId: number | null, value: number | null = null): number[] {
  render(<ProductCategoryRootSelect value={value} onChange={vi.fn()} triggerLabel="Root" onlyRootId={onlyRootId} />)
  return (optionsMock.mock.calls.at(-1)?.[0] ?? []).map((option) => option.id)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('root select locked on a parent category (spec 0411)', () => {
  it('lists every root when the request is not bound', () => {
    expect(renderRootSelect(null)).toEqual([1, 2, 3])
  })

  it('lists only the bound root', () => {
    expect(renderRootSelect(2)).toEqual([2])
  })

  it('keeps the root already saved on the row', () => {
    expect(renderRootSelect(2, 3)).toEqual([2, 3])
  })
})
