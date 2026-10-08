import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryFilterSelect } from '@/features/product-lines/product-category-filter-select'
import { ProductCategoryRootSelect } from '@/features/product-lines/product-category-root-select'
import { ProductCategoryTreeSelect } from '@/features/product-lines/product-category-tree-select'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0208 AC-019: the destination pickers built on the category tree drop an
 * inactive category with its descendants, but still list the value already
 * selected, even when it sits in an inactive branch.
 */

function node(
  id: number,
  overrides: Partial<ProductCategoryTreeNode> = {},
  children: ProductCategoryTreeNode[] = [],
): ProductCategoryTreeNode {
  return {
    id,
    name: `Category ${id}`,
    parent_id: null,
    children,
    attributes_count: 0,
    products_count: 0,
    business_function_id: null,
    requires_quote: false,
    is_selectable: true,
    is_active: true,
    is_reportable: null,
    management_mode: 'multiple',
    single_quote_per_opportunity: false,
    generates_contract: false,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
    ...overrides,
  }
}

const TREE: ProductCategoryTreeNode[] = [
  node(1, { is_selectable: false }, [
    node(2, { parent_id: 1, is_selectable: false, is_active: false }, [
      node(3, { parent_id: 2 }),
    ]),
    node(4, { parent_id: 1, is_selectable: false }, [node(5, { parent_id: 4 })]),
  ]),
  node(6, { is_active: false }, [node(7, { parent_id: 6 })]),
  node(8),
]

vi.mock('@/features/product-categories/use-product-category-tree', () => ({
  useProductCategoryTree: () => ({ data: TREE, isPending: false, isError: false, refetch: vi.fn() }),
}))

type Option = { id: number; disabled?: boolean }
const optionsMock = vi.fn<(options: Option[]) => void>()
vi.mock('@/components/ui/searchable-select', () => ({
  SearchableSelect: ({ options }: { options: Option[] }) => {
    optionsMock(options)
    return null
  },
}))

function lastIds(): string[] {
  return (optionsMock.mock.calls.at(-1)?.[0] ?? []).map((o) => `${o.id}${o.disabled ? ':disabled' : ''}`)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('destination pickers drop inactive branches (spec 0208 AC-019)', () => {
  it('root select: omits an inactive root, keeps it when it is the current value', () => {
    render(<ProductCategoryRootSelect value={null} onChange={vi.fn()} triggerLabel="root" />)
    expect(lastIds()).toEqual(['1', '8'])

    render(<ProductCategoryRootSelect value={6} onChange={vi.fn()} triggerLabel="root" />)
    expect(lastIds()).toEqual(['1', '6', '8'])
  })

  it('filter select: omits an inactive grouping child', () => {
    render(<ProductCategoryFilterSelect rootCategoryId={1} value={null} onChange={vi.fn()} triggerLabel="filter" />)
    expect(lastIds()).toEqual(['4'])
  })

  it('category select: omits the inactive branch, keeps the current value with its path as context', () => {
    const scope = { kind: 'root', rootCategoryId: 1 } as const

    render(<ProductCategoryTreeSelect value={null} onChange={vi.fn()} scope={scope} triggerLabel="category" />)
    expect(lastIds()).toEqual(['1:disabled', '4:disabled', '5'])

    render(<ProductCategoryTreeSelect value={3} onChange={vi.fn()} scope={scope} triggerLabel="category" />)
    expect(lastIds()).toEqual(['1:disabled', '2:disabled', '3', '4:disabled', '5'])
  })
})
