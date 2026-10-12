import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import { ProductCategoryForm } from '@/features/product-categories/product-category-form'
import {
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  treeNode,
} from '@/features/product-categories/product-category-test-fixtures'
import type { AttributeLayoutData, ProductCategoryDetailWithPermissions, ProductCategoryTreeNode } from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * The per-node flag rows of the rules section (create draft and detail), split
 * out of `product-category-form-body.test.tsx` (engineering.md §6 file limit),
 * with the same setup:
 *  - "Active" (spec 0208 AC-022): on by default in create mode, and under an
 *    inactive ancestor it names that ancestor, since the category is then
 *    inactive whatever its own flag says (D-1);
 *  - "Selectable" (spec 0074): on by default, never inherited.
 */

const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()
const fetchAttributeLayoutMock = vi.fn<
  (categoryId: number, context: string, formMode: string) => Promise<AttributeLayoutData>
>()
const fetchEffectiveManagerLabelsMock = vi.fn<(categoryId: number) => Promise<Record<string, string>>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: vi.fn(),
  updateProductCategory: vi.fn(),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
  fetchEffectiveManagerLabels: (categoryId: number) => fetchEffectiveManagerLabelsMock(categoryId),
  fetchAttributeLayout: (...args: [number, string, string]) => fetchAttributeLayoutMock(...args),
  saveAttributeLayout: vi.fn(),
  fetchReportColumnsCatalog: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/attributes/use-attribute-catalog', () => ({
  useAttributeCatalog: () => ({ data: [], isPending: false, isError: false, refetch: vi.fn() }),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// The parent-category picker's quick-create "+" reads `useAbilities()` via
// `Can`, which needs an `AuthProvider` this suite doesn't render (mirrors
// `product-category-business-function-field.test.tsx`).
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchAttributeLayoutMock.mockReset()
  fetchAttributeLayoutMock.mockResolvedValue({ layout: null, inherited: null, inherited_from_category: null, inherited_from_category_source: null, attributes: [] })
  fetchEffectiveManagerLabelsMock.mockReset()
  fetchEffectiveManagerLabelsMock.mockResolvedValue({})
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

function reportableTreeNode(overrides: Partial<ProductCategoryTreeNode>): ProductCategoryTreeNode {
  return treeNode({ is_reportable: null, ...overrides })
}

describe('active row (spec 0208 AC-022)', () => {
  it('create mode: the switch is on by default and no inheritance notice shows', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Active' }))

    expect(await screen.findByRole('switch', { name: 'Active' })).toBeChecked()
    expect(screen.queryByText(/Not active because/)).not.toBeInTheDocument()
  })

  it('names the inactive ancestor under the switch', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([
      reportableTreeNode({ id: 1, name: 'Electronics', is_active: false }),
    ])

    renderDetail(childCategory())

    fireEvent.click(pencilOf('isActive') as HTMLElement)

    expect(await screen.findByText('Not active because "Electronics" is not')).toBeInTheDocument()
    expect(screen.getByRole('switch', { name: 'Active' })).toBeChecked()
  })
})

describe('selectable row (spec 0074)', () => {
  it('create mode: the switch is on by default and can be turned off (AC-014)', async () => {
    render(<ProductCategoryForm mode={{ type: 'create', parentId: null }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Selectable' }))
    const selectableSwitch = await screen.findByRole('switch', { name: 'Selectable' })
    expect(selectableSwitch).toBeChecked()

    fireEvent.click(selectableSwitch)

    await waitFor(() => expect(selectableSwitch).not.toBeChecked())
  })

  it('edit mode: the switch mirrors the saved value, with no parent-driven read-only state', async () => {
    renderDetail(childCategory({ is_selectable: false }))

    // Unlike the quote flag, this one is never inherited: a child still edits it.
    fireEvent.click(pencilOf('isSelectable') as HTMLElement)
    const selectableSwitch = await screen.findByRole('switch', { name: 'Selectable' })

    expect(selectableSwitch).not.toBeChecked()
    expect(selectableSwitch).toBeEnabled()
  })
})
