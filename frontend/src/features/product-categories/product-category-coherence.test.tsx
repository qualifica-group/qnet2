import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ProductCategoryDetailView } from '@/features/product-categories/product-category-detail'
import {
  category,
  categoryWrapper,
  childCategory,
  pencilOf,
  permissionsFor,
  saveRow,
} from '@/features/product-categories/product-category-test-fixtures'
import type {
  ProductCategoryDetailWithPermissions,
  ProductCategoryTreeNode,
} from '@/features/product-categories/types'
import type { ResourceMeta } from '@/features/authorization/types'

/** Coherence between `multi_quote_work_order` and `generates_contract` (2026-10-15). */

const createProductCategoryMock = vi.fn()
const updateProductCategoryMock = vi.fn()
const fetchProductCategoryTreeMock = vi.fn<() => Promise<ProductCategoryTreeNode[]>>()

vi.mock('@/features/product-categories/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/product-categories/api')>()),
  createProductCategory: (...args: unknown[]) => createProductCategoryMock(...args),
  updateProductCategory: (...args: unknown[]) => updateProductCategoryMock(...args),
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
  fetchEffectiveAttributes: () => Promise.resolve([]),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
    '@/features/for-select/use-for-select',
  )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: () => ({
      data: { pages: [{ items: [] }] },
      isPending: false,
      isError: false,
      fetchNextPage: vi.fn(),
      hasNextPage: false,
      isFetchingNextPage: false,
      refetch: vi.fn(),
    }),
    useForSelectLabels: () => new Map(),
  }
})

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

function wrapper() {
  return categoryWrapper().wrapper
}

function renderDetail(detail: ProductCategoryDetailWithPermissions) {
  return render(<ProductCategoryDetailView category={detail} />, { wrapper: wrapper() })
}

/** The switch located by the label `MetaField` wires to it. */
const findSwitch = (name: string) => screen.findByRole('switch', { name })

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  updateProductCategoryMock.mockReset()
  fetchProductCategoryTreeMock.mockReset()
  fetchProductCategoryTreeMock.mockResolvedValue([])
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('multi-offer / contract coherence', () => {
  it('root with contract off: the multi-offer switch is locked and says why', async () => {
    renderDetail(category({ generates_contract: false, multi_quote_work_order: false }))

    fireEvent.click(pencilOf('multiQuoteWorkOrder') as HTMLElement)

    expect(await findSwitch('Multi-offer work order')).toBeDisabled()
    expect(screen.getByText('Requires Includes a contract.')).toBeInTheDocument()
  })

  it('root with contract on: the multi-offer switch stays editable', async () => {
    renderDetail(category({ generates_contract: true, multi_quote_work_order: false }))

    fireEvent.click(pencilOf('multiQuoteWorkOrder') as HTMLElement)

    expect(await findSwitch('Multi-offer work order')).not.toBeDisabled()
    expect(screen.queryByText('Requires Includes a contract.')).not.toBeInTheDocument()
  })

  it('root with multi on: the contract switch is locked and says why', async () => {
    renderDetail(category({ generates_contract: true, multi_quote_work_order: true }))

    fireEvent.click(pencilOf('generatesContract') as HTMLElement)

    const control = await findSwitch('Includes a contract')
    expect(control).toBeDisabled()
    expect(control).toBeChecked()
    expect(screen.getByText('Turn off Multi-offer work order first.')).toBeInTheDocument()
  })

  it('child: no pencil on either rule', () => {
    renderDetail(childCategory({ generates_contract: false, multi_quote_work_order: false }))

    expect(pencilOf('multiQuoteWorkOrder')).not.toBeInTheDocument()
    expect(pencilOf('generatesContract')).not.toBeInTheDocument()
  })

  it('shows the server 422 message on the field', async () => {
    updateProductCategoryMock.mockRejectedValue({
      isAxiosError: true,
      response: {
        status: 422,
        data: { success: false, message: 'Invalid', errors: { multi_quote_work_order: ['Needs a contract.'] } },
      },
    })
    renderDetail(category({ generates_contract: true, multi_quote_work_order: false }))

    fireEvent.click(pencilOf('multiQuoteWorkOrder') as HTMLElement)
    fireEvent.click(await findSwitch('Multi-offer work order'))
    saveRow()

    expect(await screen.findByText('Needs a contract.')).toBeInTheDocument()
  })
})
