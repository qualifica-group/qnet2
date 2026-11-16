import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, renderHook } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryRootSelect } from '@/features/product-lines/product-category-root-select'
import { useProductLineCellPicker } from '@/features/product-lines/use-product-line-cell-picker'
import type { CategoryUsageArea, ProductCategoryTreeNode } from '@/features/product-categories/types'

/**
 * Spec 0389 AC-007: a card picks its root only among the branches used in its
 * module; the root it already holds stays listed.
 */

function root(id: number, usageArea: CategoryUsageArea): ProductCategoryTreeNode {
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
    usage_area: usageArea,
    multiple_open_opportunities: false,
    simplified_offer_line: false,
    simplified_offer_line_override: null,
  }
}

const TREE: ProductCategoryTreeNode[] = [root(1, 'all'), root(2, 'requests'), root(3, 'opportunities')]

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

function lastIds(): number[] {
  return (optionsMock.mock.calls.at(-1)?.[0] ?? []).map((option) => option.id)
}

function renderRootSelect(area: 'requests' | 'opportunities' | undefined, value: number | null = null) {
  render(<ProductCategoryRootSelect value={value} onChange={vi.fn()} triggerLabel="Root" area={area} />)
  return lastIds()
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('root pickers follow the usage area (spec 0389)', () => {
  it('form root select: each module lists its own roots and the shared ones', () => {
    expect(renderRootSelect('requests')).toEqual([1, 2])
    expect(renderRootSelect('opportunities')).toEqual([1, 3])
  })

  it('form root select: without an area (projects, campaigns) every root is listed', () => {
    expect(renderRootSelect(undefined)).toEqual([1, 2, 3])
  })

  it('form root select: keeps the root already saved even when it belongs to the other module', () => {
    expect(renderRootSelect('requests', 3)).toEqual([1, 2, 3])
  })

  it('grid cell picker: the root step lists only the roots of the grid module', () => {
    const { result } = renderHook(() =>
      useProductLineCellPicker({ categoryTree: TREE, addingBesidePairs: false, area: 'opportunities', onPick: vi.fn() }),
    )

    expect(result.current.options.map((option) => option.id)).toEqual([1, 3])
  })
})
